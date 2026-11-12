TRUNCATE user_permissions, level_permissions, users, pages, levels RESTART IDENTITY CASCADE;

INSERT INTO levels (name, description, is_active) VALUES
    ('Super Admin', 'Akses penuh ke seluruh halaman aplikasi', 1),
    ('Manager', 'Mengelola user dan melihat dashboard', 1),
    ('Staff', 'Akses operasional harian', 1),
    ('Viewer', 'Hanya melihat dashboard', 1);

INSERT INTO pages (name, path, description, sort_order, is_active) VALUES
    ('Dashboard', '/dashboard', 'Ringkasan aplikasi dan menu yang dapat diakses', 1, 1),
    ('Manajemen User', '/users', 'Kelola data user', 2, 1),
    ('Manajemen Level', '/levels', 'Kelola data level jabatan', 3, 1),
    ('Manajemen Page', '/pages', 'Kelola data page dan menu', 4, 1),
    ('User Permission', '/permissions', 'Atur hak akses level dan user', 5, 1);

INSERT INTO users (full_name, username, email, password_hash, level_id, is_active)
SELECT 'Administrator', 'admin', 'admin@imc.test', crypt('password123', gen_salt('bf', 10)), id, 1
FROM levels WHERE name = 'Super Admin';

INSERT INTO users (full_name, username, email, password_hash, level_id, is_active)
SELECT 'Manager Operasional', 'manager', 'manager@imc.test', crypt('password123', gen_salt('bf', 10)), id, 1
FROM levels WHERE name = 'Manager';

INSERT INTO users (full_name, username, email, password_hash, level_id, is_active)
SELECT 'Staff Gudang', 'staff', 'staff@imc.test', crypt('password123', gen_salt('bf', 10)), id, 1
FROM levels WHERE name = 'Staff';

INSERT INTO users (full_name, username, email, password_hash, level_id, is_active)
SELECT 'Viewer Tamu', 'viewer', 'viewer@imc.test', crypt('password123', gen_salt('bf', 10)), id, 1
FROM levels WHERE name = 'Viewer';

INSERT INTO level_permissions (level_id, page_id)
SELECT l.id, p.id
FROM levels l
CROSS JOIN pages p
WHERE l.name = 'Super Admin';

INSERT INTO level_permissions (level_id, page_id)
SELECT l.id, p.id
FROM levels l
JOIN pages p ON p.path IN ('/dashboard', '/users')
WHERE l.name = 'Manager';

INSERT INTO level_permissions (level_id, page_id)
SELECT l.id, p.id
FROM levels l
JOIN pages p ON p.path = '/dashboard'
WHERE l.name IN ('Staff', 'Viewer');

INSERT INTO user_permissions (user_id, page_id, effect)
SELECT u.id, p.id, 'allow'
FROM users u
JOIN pages p ON p.path = '/users'
WHERE u.username = 'staff';

INSERT INTO user_permissions (user_id, page_id, effect)
SELECT u.id, p.id, 'deny'
FROM users u
JOIN pages p ON p.path = '/users'
WHERE u.username = 'manager';
