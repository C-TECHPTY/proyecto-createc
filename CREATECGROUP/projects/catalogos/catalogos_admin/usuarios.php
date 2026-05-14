<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login(['admin']);

$roles = [
    'admin' => 'Administrador',
    'sales' => 'Ventas',
    'billing' => 'Facturacion',
    'operator' => 'Operador',
    'vendor' => 'Vendedor',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $action = (string) ($_POST['action'] ?? 'create');
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'create' || ($action === 'update' && $userId > 0)) {
        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $emailRaw = trim((string) ($_POST['email'] ?? ''));
        $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ? $emailRaw : '';
        $role = (string) ($_POST['role'] ?? 'vendor');
        $sellerId = (int) ($_POST['seller_id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($username === '' || $fullName === '' || !array_key_exists($role, $roles)) {
            flash_set('error', 'Usuario, nombre y rol son obligatorios.');
            header('Location: usuarios.php' . ($userId ? '?edit=' . $userId : ''));
            exit;
        }

        if ($role === 'vendor' && $sellerId <= 0) {
            flash_set('error', 'Un usuario vendedor debe tener un vendedor asignado.');
            header('Location: usuarios.php' . ($userId ? '?edit=' . $userId : ''));
            exit;
        }

        $sellerValue = $role === 'vendor' ? $sellerId : null;

        if ($action === 'create') {
            if ($password === '') {
                flash_set('error', 'La contrasena inicial es obligatoria.');
                header('Location: usuarios.php');
                exit;
            }

            try {
                db()->prepare(
                    'INSERT INTO catalog_users (username, password_hash, full_name, email, role, seller_id, is_active)
                     VALUES (:username, :password_hash, :full_name, :email, :role, :seller_id, :is_active)'
                )->execute([
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'full_name' => $fullName,
                    'email' => $email,
                    'role' => $role,
                    'seller_id' => $sellerValue,
                    'is_active' => $isActive,
                ]);
                audit_log('user.created', 'catalog_users', (int) db()->lastInsertId(), [
                    'username' => $username,
                    'role' => $role,
                ]);
                flash_set('success', 'Usuario creado. Ya puede iniciar sesion.');
            } catch (PDOException $exception) {
                flash_set('error', str_contains($exception->getMessage(), 'Duplicate') ? 'Ese usuario ya existe.' : 'No se pudo crear el usuario.');
            }
        } else {
            $sets = [
                'username = :username',
                'full_name = :full_name',
                'email = :email',
                'role = :role',
                'seller_id = :seller_id',
                'is_active = :is_active',
                'updated_at = NOW()',
            ];
            $params = [
                'id' => $userId,
                'username' => $username,
                'full_name' => $fullName,
                'email' => $email,
                'role' => $role,
                'seller_id' => $sellerValue,
                'is_active' => $isActive,
            ];
            if ($password !== '') {
                $sets[] = 'password_hash = :password_hash';
                $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            try {
                db()->prepare('UPDATE catalog_users SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
                audit_log('user.updated', 'catalog_users', $userId, [
                    'username' => $username,
                    'role' => $role,
                    'password_changed' => $password !== '',
                ]);
                flash_set('success', $password !== '' ? 'Usuario actualizado y contrasena cambiada.' : 'Usuario actualizado.');
            } catch (PDOException $exception) {
                flash_set('error', str_contains($exception->getMessage(), 'Duplicate') ? 'Ese usuario ya existe.' : 'No se pudo actualizar el usuario.');
            }
        }
    }

    if ($action === 'toggle' && $userId > 0) {
        if ($userId === (int) (current_user()['id'] ?? 0)) {
            flash_set('error', 'No puedes desactivar tu propio usuario mientras estas conectado.');
        } else {
            db()->prepare(
                'UPDATE catalog_users
                 SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = NOW()
                 WHERE id = :id'
            )->execute(['id' => $userId]);
            audit_log('user.toggled', 'catalog_users', $userId);
            flash_set('success', 'Estado del usuario actualizado.');
        }
    }

    header('Location: usuarios.php');
    exit;
}

$sellers = admin_table_exists('sellers')
    ? db()->query("SELECT id, name, code FROM sellers WHERE is_active = 1 ORDER BY name ASC")->fetchAll()
    : [];

$users = db()->query(
    "SELECT u.*, s.name AS seller_name, s.code AS seller_code
     FROM catalog_users u
     LEFT JOIN sellers s ON s.id = u.seller_id
     ORDER BY u.role ASC, u.full_name ASC, u.username ASC"
)->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
$editUser = null;
foreach ($users as $userRow) {
    if ((int) $userRow['id'] === $editId) {
        $editUser = $userRow;
        break;
    }
}

admin_header('Usuarios', 'usuarios.php');
?>
<div class="split">
    <section class="card">
        <div class="toolbar">
            <strong><?= $editUser ? 'Editar usuario' : 'Nuevo usuario' ?></strong>
            <?php if ($editUser): ?><a class="button" href="usuarios.php">Nuevo</a><?php endif; ?>
        </div>
        <form class="form-grid" method="post" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
            <input type="hidden" name="user_id" value="<?= (int) ($editUser['id'] ?? 0) ?>">
            <label><span>Usuario</span><input type="text" name="username" value="<?= html_escape($editUser['username'] ?? '') ?>" required autocomplete="new-password"></label>
            <label><span>Nombre completo</span><input type="text" name="full_name" value="<?= html_escape($editUser['full_name'] ?? '') ?>" required></label>
            <label><span>Correo</span><input type="email" name="email" value="<?= html_escape($editUser['email'] ?? '') ?>"></label>
            <label>
                <span>Rol</span>
                <select name="role" id="roleSelect" required>
                    <?php foreach ($roles as $value => $label): ?>
                        <option value="<?= html_escape($value) ?>" <?= $value === ($editUser['role'] ?? 'vendor') ? 'selected' : '' ?>><?= html_escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="wide">
                <span>Vendedor asignado</span>
                <select name="seller_id" id="sellerSelect">
                    <option value="">Sin asignar</option>
                    <?php foreach ($sellers as $seller): ?>
                        <option value="<?= (int) $seller['id'] ?>" <?= (int) ($editUser['seller_id'] ?? 0) === (int) $seller['id'] ? 'selected' : '' ?>>
                            <?= html_escape($seller['name']) ?> (<?= html_escape($seller['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="wide">
                <span><?= $editUser ? 'Nueva contrasena' : 'Contrasena inicial' ?></span>
                <input type="password" name="password" <?= $editUser ? '' : 'required' ?> autocomplete="new-password">
            </label>
            <label class="checkbox-line"><input type="checkbox" name="is_active" <?= !$editUser || (int) $editUser['is_active'] === 1 ? 'checked' : '' ?>><span>Activo</span></label>
            <div class="wide"><button class="button--primary" type="submit"><?= $editUser ? 'Guardar usuario' : 'Crear usuario' ?></button></div>
        </form>
        <p class="muted">Los vendedores entran por <code>catalogos_admin/login.php</code> y el sistema los envia automaticamente al panel vendedor.</p>
    </section>
    <section class="card">
        <div class="toolbar"><strong>Accesos registrados</strong><span class="pill"><?= count($users) ?> usuarios</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Usuario</th><th>Rol</th><th>Vendedor</th><th>Estado</th><th>Ultimo acceso</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($users as $userRow): ?>
                    <tr>
                        <td><strong><?= html_escape($userRow['username']) ?></strong><div class="muted"><?= html_escape($userRow['full_name']) ?><br><?= html_escape($userRow['email']) ?></div></td>
                        <td><?= html_escape($roles[$userRow['role']] ?? $userRow['role']) ?></td>
                        <td><?= html_escape(($userRow['seller_name'] ?? '') ?: 'Sin asignar') ?></td>
                        <td><?= admin_status_badge((int) $userRow['is_active'] === 1 ? 'active' : 'inactive') ?></td>
                        <td><?= html_escape($userRow['last_login_at'] ?? '') ?></td>
                        <td>
                            <div class="toolbar__actions">
                                <a class="button" href="usuarios.php?edit=<?= (int) $userRow['id'] ?>">Editar</a>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="user_id" value="<?= (int) $userRow['id'] ?>">
                                    <button type="submit"><?= (int) $userRow['is_active'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script>
(() => {
  const role = document.getElementById("roleSelect");
  const seller = document.getElementById("sellerSelect");
  const sync = () => {
    if (!role || !seller) return;
    seller.required = role.value === "vendor";
    seller.closest("label").style.opacity = role.value === "vendor" ? "1" : ".62";
  };
  role?.addEventListener("change", sync);
  sync();
})();
</script>
<?php admin_footer(); ?>
