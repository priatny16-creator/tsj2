CREATE DATABASE IF NOT EXISTS tszh_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tszh_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'resident') DEFAULT 'resident',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE apartments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(20) NOT NULL,
    floor INT NOT NULL,
    area DECIMAL(10,2) NOT NULL,
    rooms INT NOT NULL,
    owner_id INT,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE residents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    apartment_id INT NOT NULL,
    phone VARCHAR(20),
    registered_at DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tariffs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    price_per_unit DECIMAL(10,4) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE meter_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    apartment_id INT NOT NULL,
    tariff_id INT NOT NULL,
    previous_value DECIMAL(12,3) DEFAULT 0,
    current_value DECIMAL(12,3) NOT NULL,
    reading_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE,
    FOREIGN KEY (tariff_id) REFERENCES tariffs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    apartment_id INT NOT NULL,
    period VARCHAR(7) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_paid TINYINT(1) DEFAULT 0,
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    tariff_id INT NOT NULL,
    meter_reading_id INT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    price_per_unit DECIMAL(10,4) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    FOREIGN KEY (tariff_id) REFERENCES tariffs(id) ON DELETE CASCADE,
    FOREIGN KEY (meter_reading_id) REFERENCES meter_readings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO users (login, password, full_name, role) VALUES
('admin', '$2y$10$YWIxZTRiMTVkOGYwZjRlZeC0KBn5FG.FVHbPGQSZhdmDHG3wa2sFi', 'Администратор', 'admin'),
('ivanov', '$2y$10$YWIxZTRiMTVkOGYwZjRlZeC0KBn5FG.FVHbPGQSZhdmDHG3wa2sFi', 'Иванов Иван Иванович', 'resident'),
('petrova', '$2y$10$YWIxZTRiMTVkOGYwZjRlZeC0KBn5FG.FVHbPGQSZhdmDHG3wa2sFi', 'Петрова Мария Сергеевна', 'resident');

INSERT INTO apartments (number, floor, area, rooms, owner_id) VALUES
('1', 1, 45.50, 2, 2),
('2', 1, 62.30, 3, 3),
('3', 2, 38.00, 1, NULL),
('4', 2, 55.80, 2, NULL);

INSERT INTO residents (user_id, apartment_id, phone, registered_at) VALUES
(2, 1, '+7 (999) 123-45-67', '2020-01-15'),
(3, 2, '+7 (999) 765-43-21', '2019-06-10');

INSERT INTO tariffs (name, unit, price_per_unit) VALUES
('Холодная вода', 'куб.м', 40.4800),
('Горячая вода', 'куб.м', 205.1500),
('Электроэнергия', 'кВт*ч', 6.7300),
('Газ', 'куб.м', 7.1200),
('Отопление', 'Гкал', 2546.8500);
