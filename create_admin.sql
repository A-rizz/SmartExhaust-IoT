-- Create a new admin user
INSERT INTO users (username, password, email, is_verified, is_admin) 
VALUES (
    'admin', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password is 'password'
    'admin@example.com',
    TRUE,
    TRUE
);

-- Verify the admin was created
SELECT id, username, email, is_admin, is_verified FROM users WHERE username = 'admin'; 