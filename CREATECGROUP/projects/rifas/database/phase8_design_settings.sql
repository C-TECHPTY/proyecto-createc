ALTER TABLE raffles
  ADD COLUMN IF NOT EXISTS background_color VARCHAR(20) NOT NULL DEFAULT '#eaf8ff' AFTER accent_color,
  ADD COLUMN IF NOT EXISTS grid_style VARCHAR(40) NOT NULL DEFAULT 'soft_cards' AFTER background_color;

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
