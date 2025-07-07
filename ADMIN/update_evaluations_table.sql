-- Add missing columns to detailed_evaluations table for admin functionality

-- Add reviewed column if it doesn't exist
ALTER TABLE detailed_evaluations 
ADD COLUMN IF NOT EXISTS reviewed TINYINT(1) DEFAULT 0 COMMENT 'Whether evaluation has been reviewed by admin';

-- Add admin suggestion columns if they don't exist
ALTER TABLE detailed_evaluations 
ADD COLUMN IF NOT EXISTS admin_suggestion TEXT NULL COMMENT 'Admin suggestion for improvement';

ALTER TABLE detailed_evaluations 
ADD COLUMN IF NOT EXISTS admin_suggestion_date DATETIME NULL COMMENT 'Date when admin suggestion was added';

-- Add index for better performance
ALTER TABLE detailed_evaluations 
ADD INDEX IF NOT EXISTS idx_reviewed (reviewed);

ALTER TABLE detailed_evaluations 
ADD INDEX IF NOT EXISTS idx_eval_date (eval_date);

-- Update existing records to have reviewed = 0 if NULL
UPDATE detailed_evaluations SET reviewed = 0 WHERE reviewed IS NULL;
