-- Create database structure for Balaji Tex Management
USE balaji_tex;

-- Create companies table
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    created_date DATE NOT NULL
);

-- Create workers table
CREATE TABLE IF NOT EXISTS workers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

-- Create yarn_types table
CREATE TABLE IF NOT EXISTS yarn_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

-- Create stocks table
CREATE TABLE IF NOT EXISTS stocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    yarn_type_id INT,
    cotton_type VARCHAR(255),
    bag_weight DECIMAL(10,3) DEFAULT 0,
    total_bags INT DEFAULT 0,
    sold_bags INT DEFAULT 0,
    sold_cones INT DEFAULT 0,
    sold_weight DECIMAL(10,3) DEFAULT 0,
    stock_type ENUM('chippam', 'bag') DEFAULT 'bag',
    date DATE NOT NULL,
    notes TEXT
);

-- Create work_logs table
CREATE TABLE IF NOT EXISTS work_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    worker_id INT NOT NULL,
    work_date DATE NOT NULL,
    warps_count INT NOT NULL DEFAULT 0,
    rate_per_warp DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount DECIMAL(10,2) GENERATED ALWAYS AS (warps_count * rate_per_warp) STORED,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (worker_id) REFERENCES workers(id)
);

-- Create advances table
CREATE TABLE IF NOT EXISTS advances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    worker_id INT NOT NULL,
    advance_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    settled TINYINT(1) DEFAULT 0,
    note TEXT,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (worker_id) REFERENCES workers(id)
);

-- Create purchased_stocks table
CREATE TABLE IF NOT EXISTS purchased_stocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    yarn_type_id INT,
    supplier_name VARCHAR(255) NOT NULL,
    date_purchased DATE NOT NULL,
    bag_count INT NOT NULL,
    weight_per_bag DECIMAL(10,3) NOT NULL,
    total_weight DECIMAL(10,3) GENERATED ALWAYS AS (bag_count * weight_per_bag) STORED,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (yarn_type_id) REFERENCES yarn_types(id)
);

-- Insert default company
INSERT INTO companies (company_name, created_date) 
VALUES ('BALAJI TEX', CURDATE())
ON DUPLICATE KEY UPDATE company_name = company_name;
