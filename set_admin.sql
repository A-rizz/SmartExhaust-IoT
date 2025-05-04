-- Method 1: Set a specific user as admin by username
UPDATE users SET is_admin = TRUE WHERE username = 'your_username';

-- Method 2: Set a specific user as admin by ID
UPDATE users SET is_admin = TRUE WHERE id = 1;

-- Method 3: Set multiple users as admin
UPDATE users SET is_admin = TRUE WHERE username IN ('username1', 'username2');

-- To verify the changes
SELECT id, username, is_admin FROM users WHERE is_admin = TRUE; 