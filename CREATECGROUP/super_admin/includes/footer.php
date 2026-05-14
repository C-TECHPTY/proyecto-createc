<?php
declare(strict_types=1);

function sa_footer(): void
{
    if (sa_current_user()) {
        echo '</main></div>';
    }
    echo '</body></html>';
}
