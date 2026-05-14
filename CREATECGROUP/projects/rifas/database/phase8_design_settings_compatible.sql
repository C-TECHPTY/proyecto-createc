SET @db_name := DATABASE();

SET @add_background_color := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE raffles ADD COLUMN background_color VARCHAR(20) NOT NULL DEFAULT ''#eaf8ff'' AFTER accent_color',
    'SELECT ''background_color already exists'''
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'raffles'
    AND COLUMN_NAME = 'background_color'
);

PREPARE stmt FROM @add_background_color;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_grid_style := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE raffles ADD COLUMN grid_style VARCHAR(40) NOT NULL DEFAULT ''soft_cards'' AFTER background_color',
    'SELECT ''grid_style already exists'''
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'raffles'
    AND COLUMN_NAME = 'grid_style'
);

PREPARE stmt FROM @add_grid_style;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE raffles
SET
  theme = CASE WHEN theme = 'afro_glam' THEN 'clean_sky' ELSE theme END,
  primary_color = CASE WHEN primary_color = '#d6a84f' THEN '#38aeea' ELSE primary_color END,
  accent_color = CASE WHEN accent_color = '#e0528d' THEN '#f06292' ELSE accent_color END,
  background_color = COALESCE(NULLIF(background_color, ''), '#eaf8ff'),
  grid_style = COALESCE(NULLIF(grid_style, ''), 'soft_cards')
WHERE theme = 'afro_glam'
   OR primary_color = '#d6a84f'
   OR background_color IS NULL
   OR grid_style IS NULL;
