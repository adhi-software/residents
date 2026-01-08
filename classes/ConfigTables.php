<?php
/**
 ***********************************************************************************************
 * Creator for Residents plugin tables (multi-DB: MySQL/PostgreSQL)
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');

class ConfigTables
{
    private const TABLE_DEFINITION_MYSQL_BILL_INVOICES = '
    biv_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    biv_status VARCHAR(30) NOT NULL DEFAULT \'O\',
    biv_is_paid TINYINT(1) NOT NULL DEFAULT 0,
    biv_number_index INT UNSIGNED NOT NULL,
    biv_number VARCHAR(50) NOT NULL,
    biv_date DATE NULL,
    biv_type VARCHAR(30) NOT NULL DEFAULT \'I\',
    biv_usr_id INT UNSIGNED NULL,
    biv_start_date DATE NULL,
    biv_end_date DATE NULL,
    biv_due_date DATE NULL,
    biv_notes TEXT NULL,
    biv_usr_id_create INT UNSIGNED DEFAULT NULL,
    biv_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    biv_usr_id_change INT UNSIGNED DEFAULT NULL,
    biv_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (biv_id)
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_INVOICES_HIST = '
    ivh_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    biv_id INT UNSIGNED NOT NULL,
    biv_status VARCHAR(30) NOT NULL DEFAULT \'O\',
    biv_is_paid TINYINT(1) NOT NULL DEFAULT 0,
    biv_number_index INT UNSIGNED NOT NULL,
    biv_number VARCHAR(50) NOT NULL,
    biv_date DATE NULL,
    biv_type VARCHAR(30) NOT NULL DEFAULT \'I\',
    biv_usr_id INT UNSIGNED NULL,
    biv_start_date DATE NULL,
    biv_end_date DATE NULL,
    biv_due_date DATE NULL,
    biv_notes TEXT NULL,
    biv_usr_id_create INT UNSIGNED DEFAULT NULL,
    biv_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    biv_usr_id_change INT UNSIGNED DEFAULT NULL,
    biv_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    ivh_action VARCHAR(20) NOT NULL,
    ivh_usr_id INT UNSIGNED DEFAULT NULL,
    ivh_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ivh_id)
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_INVOICES = '
    biv_id SERIAL PRIMARY KEY,
    biv_status VARCHAR(30) NOT NULL DEFAULT \'O\',
    biv_is_paid INTEGER NOT NULL DEFAULT 0,
    biv_number_index INTEGER NOT NULL,
    biv_number VARCHAR(50) NOT NULL,
    biv_date DATE NULL,
    biv_type VARCHAR(30) NOT NULL DEFAULT \'I\',
    biv_usr_id INTEGER NULL,
    biv_start_date DATE NULL,
    biv_end_date DATE NULL,
    biv_due_date DATE NULL,
    biv_notes TEXT NULL,
    biv_usr_id_create INTEGER DEFAULT NULL,
    biv_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    biv_usr_id_change INTEGER DEFAULT NULL,
    biv_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_INVOICES_HIST = '
    ivh_id SERIAL PRIMARY KEY,
    biv_id INTEGER NOT NULL,
    biv_status VARCHAR(30) NOT NULL DEFAULT \'O\',
    biv_is_paid INTEGER NOT NULL DEFAULT 0,
    biv_number_index INTEGER NOT NULL,
    biv_number VARCHAR(50) NOT NULL,
    biv_date DATE NULL,
    biv_type VARCHAR(30) NOT NULL DEFAULT \'I\',
    biv_usr_id INTEGER NULL,
    biv_start_date DATE NULL,
    biv_end_date DATE NULL,
    biv_due_date DATE NULL,
    biv_notes TEXT NULL,
    biv_usr_id_create INTEGER DEFAULT NULL,
    biv_timestamp_create TIMESTAMP DEFAULT NULL,
    biv_usr_id_change INTEGER DEFAULT NULL,
    biv_timestamp_change TIMESTAMP DEFAULT NULL,
    ivh_action VARCHAR(20) NOT NULL,
    ivh_usr_id INTEGER DEFAULT NULL,
    ivh_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_INVOICE_ITEMS = '
    bii_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bii_inv_id INT UNSIGNED NOT NULL,
    bii_chg_id INT UNSIGNED NOT NULL DEFAULT 0,
    bii_name VARCHAR(255) NOT NULL,
    bii_start_date DATE NULL,
    bii_end_date DATE NULL,
    bii_type VARCHAR(30) NULL,
    bii_currency VARCHAR(8) NULL,
    bii_rate DECIMAL(12,2) NULL,
    bii_quantity DECIMAL(12,2) NULL,
    bii_amount DECIMAL(12,2) NULL,
    bii_usr_id_create INT UNSIGNED DEFAULT NULL,
    bii_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    bii_usr_id_change INT UNSIGNED DEFAULT NULL,
    bii_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (bii_id)
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_INVOICE_ITEMS_HIST = '
    iih_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bii_id INT UNSIGNED NOT NULL,
    bii_inv_id INT UNSIGNED NOT NULL,
    bii_chg_id INT UNSIGNED NOT NULL DEFAULT 0,
    bii_name VARCHAR(255) NOT NULL,
    bii_start_date DATE NULL,
    bii_end_date DATE NULL,
    bii_type VARCHAR(30) NULL,
    bii_currency VARCHAR(8) NULL,
    bii_rate DECIMAL(12,2) NULL,
    bii_quantity DECIMAL(12,2) NULL,
    bii_amount DECIMAL(12,2) NULL,
    bii_usr_id_create INT UNSIGNED DEFAULT NULL,
    bii_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    bii_usr_id_change INT UNSIGNED DEFAULT NULL,
    bii_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    iih_action VARCHAR(20) NOT NULL,
    iih_usr_id INT UNSIGNED DEFAULT NULL,
    iih_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (iih_id)
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_INVOICE_ITEMS = '
    bii_id SERIAL PRIMARY KEY,
    bii_inv_id INTEGER NOT NULL,
    bii_chg_id INTEGER NOT NULL DEFAULT 0,
    bii_name VARCHAR(255) NOT NULL,
    bii_start_date DATE NULL,
    bii_end_date DATE NULL,
    bii_type VARCHAR(30) NULL,
    bii_currency VARCHAR(8) NULL,
    bii_rate NUMERIC(12,2) NULL,
    bii_quantity NUMERIC(12,2) NULL,
    bii_amount NUMERIC(12,2) NULL,
    bii_usr_id_create INTEGER DEFAULT NULL,
    bii_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bii_usr_id_change INTEGER DEFAULT NULL,
    bii_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_INVOICE_ITEMS_HIST = '
    iih_id SERIAL PRIMARY KEY,
    bii_id INTEGER NOT NULL,
    bii_inv_id INTEGER NOT NULL,
    bii_chg_id INTEGER NOT NULL DEFAULT 0,
    bii_name VARCHAR(255) NOT NULL,
    bii_start_date DATE NULL,
    bii_end_date DATE NULL,
    bii_type VARCHAR(30) NULL,
    bii_currency VARCHAR(8) NULL,
    bii_rate NUMERIC(12,2) NULL,
    bii_quantity NUMERIC(12,2) NULL,
    bii_amount NUMERIC(12,2) NULL,
    bii_usr_id_create INTEGER DEFAULT NULL,
    bii_timestamp_create TIMESTAMP DEFAULT NULL,
    bii_usr_id_change INTEGER DEFAULT NULL,
    bii_timestamp_change TIMESTAMP DEFAULT NULL,
    iih_action VARCHAR(20) NOT NULL,
    iih_usr_id INTEGER DEFAULT NULL,
    iih_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_PAYMENTS = '
    bpa_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bpa_status VARCHAR(30) NOT NULL,
    bpa_date TIMESTAMP NULL,
    bpa_pay_type VARCHAR(30) NULL,
    bpa_pg_pay_method VARCHAR(30) NULL,
    bpa_trans_id VARCHAR(30) NULL,
    bpa_bank_ref_no VARCHAR(255) NULL,
    bpa_usr_id INT UNSIGNED NULL,
    bpa_org_id INT UNSIGNED NULL,
    bpa_usr_id_create INT UNSIGNED DEFAULT NULL,
    bpa_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    bpa_usr_id_change INT UNSIGNED DEFAULT NULL,
    bpa_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (bpa_id)
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_PAYMENTS_HIST = '
    pah_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bpa_id INT UNSIGNED NOT NULL,
    bpa_status VARCHAR(30) NOT NULL,
    bpa_date TIMESTAMP NULL,
    bpa_pay_type VARCHAR(30) NULL,
    bpa_pg_pay_method VARCHAR(30) NULL,
    bpa_trans_id VARCHAR(30) NULL,
    bpa_bank_ref_no VARCHAR(255) NULL,
    bpa_usr_id INT UNSIGNED NULL,
    bpa_org_id INT UNSIGNED NULL,
    bpa_usr_id_create INT UNSIGNED DEFAULT NULL,
    bpa_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    bpa_usr_id_change INT UNSIGNED DEFAULT NULL,
    bpa_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    pah_action VARCHAR(20) NOT NULL,
    pah_usr_id INT UNSIGNED DEFAULT NULL,
    pah_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (pah_id)
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_PAYMENTS = '
    bpa_id SERIAL PRIMARY KEY,
    bpa_status VARCHAR(30) NOT NULL,
    bpa_date TIMESTAMP NULL,
    bpa_pay_type VARCHAR(30) NULL,
    bpa_pg_pay_method VARCHAR(30) NULL,
    bpa_trans_id VARCHAR(30) NULL,
    bpa_bank_ref_no VARCHAR(255) NULL,
    bpa_usr_id INTEGER NULL,
    bpa_org_id INTEGER NULL,
    bpa_usr_id_create INTEGER DEFAULT NULL,
    bpa_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bpa_usr_id_change INTEGER DEFAULT NULL,
    bpa_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_PAYMENTS_HIST = '
    pah_id SERIAL PRIMARY KEY,
    bpa_id INTEGER NOT NULL,
    bpa_status VARCHAR(30) NOT NULL,
    bpa_date TIMESTAMP NULL,
    bpa_pay_type VARCHAR(30) NULL,
    bpa_pg_pay_method VARCHAR(30) NULL,
    bpa_trans_id VARCHAR(30) NULL,
    bpa_bank_ref_no VARCHAR(255) NULL,
    bpa_usr_id INTEGER NULL,
    bpa_org_id INTEGER NULL,
    bpa_usr_id_create INTEGER DEFAULT NULL,
    bpa_timestamp_create TIMESTAMP DEFAULT NULL,
    bpa_usr_id_change INTEGER DEFAULT NULL,
    bpa_timestamp_change TIMESTAMP DEFAULT NULL,
    pah_action VARCHAR(20) NOT NULL,
    pah_usr_id INTEGER DEFAULT NULL,
    pah_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_PAYMENT_ITEMS = '
    bpi_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bpi_payment_id INT UNSIGNED NULL,
    bpi_inv_id INT UNSIGNED NULL,
    bpi_amount DECIMAL(16,2) NULL,
    bpi_currency VARCHAR(5) NULL,
    bpi_usr_id INT UNSIGNED NULL,
    bpi_org_id INT UNSIGNED NULL,
    bpi_usr_id_create INT UNSIGNED NULL,
    bpi_usr_id_change INT UNSIGNED NULL,
    bpi_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    bpi_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (bpi_id)
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_PAYMENT_ITEMS_HIST = '
    pih_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bpi_id INT UNSIGNED NOT NULL,
    bpi_payment_id INT UNSIGNED NULL,
    bpi_inv_id INT UNSIGNED NULL,
    bpi_amount DECIMAL(16,2) NULL,
    bpi_currency VARCHAR(5) NULL,
    bpi_usr_id INT UNSIGNED NULL,
    bpi_org_id INT UNSIGNED NULL,
    bpi_usr_id_create INT UNSIGNED NULL,
    bpi_usr_id_change INT UNSIGNED NULL,
    bpi_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    bpi_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    pih_action VARCHAR(20) NOT NULL,
    pih_usr_id INT UNSIGNED DEFAULT NULL,
    pih_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (pih_id)
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_PAYMENT_ITEMS = '
    bpi_id SERIAL PRIMARY KEY,
    bpi_payment_id INTEGER NULL,
    bpi_inv_id INTEGER NULL,
    bpi_amount NUMERIC(16,2) NULL,
    bpi_currency VARCHAR(5) NULL,
    bpi_usr_id INTEGER NULL,
    bpi_org_id INTEGER NULL,
    bpi_usr_id_create INTEGER NULL,
    bpi_usr_id_change INTEGER NULL,
    bpi_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bpi_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_PAYMENT_ITEMS_HIST = '
    pih_id SERIAL PRIMARY KEY,
    bpi_id INTEGER NOT NULL,
    bpi_payment_id INTEGER NULL,
    bpi_inv_id INTEGER NULL,
    bpi_amount NUMERIC(16,2) NULL,
    bpi_currency VARCHAR(5) NULL,
    bpi_usr_id INTEGER NULL,
    bpi_org_id INTEGER NULL,
    bpi_usr_id_create INTEGER DEFAULT NULL,
    bpi_usr_id_change INTEGER DEFAULT NULL,
    bpi_timestamp_create TIMESTAMP DEFAULT NULL,
    bpi_timestamp_change TIMESTAMP DEFAULT NULL,
    pih_action VARCHAR(20) NOT NULL,
    pih_usr_id INTEGER DEFAULT NULL,
    pih_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_TRANS = '
    btr_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    btr_pg_id VARCHAR(30) NULL,
    btr_bank_ref_no VARCHAR(255) NULL,
    btr_status VARCHAR(5) NULL,
    btr_amount DECIMAL(16,2) NULL,
    btr_currency VARCHAR(5) NULL,
    btr_payment_id INT UNSIGNED NULL,
    btr_usr_id INT UNSIGNED NULL,
    btr_org_id INT UNSIGNED NULL,
    btr_pg_pay_method VARCHAR(255) NULL,
    btr_pg_msg VARCHAR(255) NULL,
    btr_pg_trans_date TIMESTAMP NULL,
    btr_pg_request TEXT NULL,
    btr_pg_response TEXT NULL,
    btr_usr_id_create INT UNSIGNED NULL,
    btr_usr_id_change INT UNSIGNED NULL,
    btr_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    btr_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (btr_id)
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_TRANS = '
    btr_id SERIAL PRIMARY KEY,
    btr_pg_id VARCHAR(30) NULL,
    btr_bank_ref_no VARCHAR(255) NULL,
    btr_status VARCHAR(5) NULL,
    btr_amount NUMERIC(16,2) NULL,
    btr_currency VARCHAR(5) NULL,
    btr_payment_id INTEGER NULL,
    btr_usr_id INTEGER NULL,
    btr_org_id INTEGER NULL,
    btr_pg_pay_method VARCHAR(255) NULL,
    btr_pg_msg VARCHAR(255) NULL,
    btr_pg_trans_date TIMESTAMP NULL,
    btr_pg_request TEXT NULL,
    btr_pg_response TEXT NULL,
    btr_usr_id_create INTEGER NULL,
    btr_usr_id_change INTEGER NULL,
    btr_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    btr_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_TRANS_ITEMS = '
    bti_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bti_pg_payment_id INT UNSIGNED NULL,
    bti_inv_id INT UNSIGNED NULL,
    bti_amount DECIMAL(16,2) NULL,
    bti_currency VARCHAR(5) NULL,
    bti_usr_id INT UNSIGNED NULL,
    bti_org_id INT UNSIGNED NULL,
    bti_usr_id_create INT UNSIGNED NULL,
    bti_usr_id_change INT UNSIGNED NULL,
    bti_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    bti_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (bti_id)
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_TRANS_ITEMS = '
    bti_id SERIAL PRIMARY KEY,
    bti_pg_payment_id INTEGER NULL,
    bti_inv_id INTEGER NULL,
    bti_amount NUMERIC(16,2) NULL,
    bti_currency VARCHAR(5) NULL,
    bti_usr_id INTEGER NULL,
    bti_org_id INTEGER NULL,
    bti_usr_id_create INTEGER NULL,
    bti_usr_id_change INTEGER NULL,
    bti_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bti_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_CHARGES = '
    bch_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bch_name VARCHAR(150) NOT NULL,
    bch_period VARCHAR(50) NOT NULL,
    bch_amount DECIMAL(12,2) NOT NULL,
    bch_role_ids TEXT NULL,
    bch_usr_id_create INT UNSIGNED DEFAULT NULL,
    bch_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    bch_usr_id_change INT UNSIGNED DEFAULT NULL,
    bch_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (bch_id)
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_CHARGES_HIST = '
    chh_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bch_id INT UNSIGNED NOT NULL,
    bch_name VARCHAR(150) NOT NULL,
    bch_period VARCHAR(50) NOT NULL,
    bch_amount DECIMAL(12,2) NOT NULL,
    bch_role_ids TEXT NULL,
    bch_usr_id_create INT UNSIGNED DEFAULT NULL,
    bch_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    bch_usr_id_change INT UNSIGNED DEFAULT NULL,
    bch_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    chh_action VARCHAR(20) NOT NULL,
    chh_usr_id INT UNSIGNED DEFAULT NULL,
    chh_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (chh_id)
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_CHARGES = '
    bch_id SERIAL PRIMARY KEY,
    bch_name VARCHAR(150) NOT NULL,
    bch_period VARCHAR(50) NOT NULL,
    bch_amount NUMERIC(12,2) NOT NULL,
    bch_role_ids TEXT NULL,
    bch_usr_id_create INTEGER DEFAULT NULL,
    bch_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bch_usr_id_change INTEGER DEFAULT NULL,
    bch_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_CHARGES_HIST = '
    chh_id SERIAL PRIMARY KEY,
    bch_id INTEGER NOT NULL,
    bch_name VARCHAR(150) NOT NULL,
    bch_period VARCHAR(50) NOT NULL,
    bch_amount NUMERIC(12,2) NOT NULL,
    bch_role_ids TEXT NULL,
    bch_usr_id_create INTEGER DEFAULT NULL,
    bch_timestamp_create TIMESTAMP DEFAULT NULL,
    bch_usr_id_change INTEGER DEFAULT NULL,
    bch_timestamp_change TIMESTAMP DEFAULT NULL,
    chh_action VARCHAR(20) NOT NULL,
    chh_usr_id INTEGER DEFAULT NULL,
    chh_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';
    
    private const TABLE_DEFINITION_MYSQL_BILL_DEVICES = '
    bde_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bde_device_id VARCHAR(100) NOT NULL,
    bde_usr_id INT UNSIGNED NULL,
    bde_is_active TINYINT(1) NOT NULL DEFAULT 0,
    bde_active_date DATETIME NULL,
    bde_api_key VARCHAR(50) NULL,
    bde_platform VARCHAR(50) NOT NULL,
    bde_brand VARCHAR(50) NOT NULL,
    bde_model VARCHAR(50) NOT NULL,
    bde_usr_id_create INT UNSIGNED DEFAULT NULL,
    bde_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    bde_usr_id_change INT UNSIGNED DEFAULT NULL,
    bde_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (bde_id)
    ';
    
    private const TABLE_DEFINITION_PGSQL_BILL_DEVICES = '
    bde_id SERIAL PRIMARY KEY,
    bde_device_id VARCHAR(100) NOT NULL,
    bde_usr_id INTEGER NULL,
    bde_is_active INTEGER NOT NULL DEFAULT 0,
    bde_active_date TIMESTAMP NULL,
    bde_api_key VARCHAR(50) NULL,
    bde_platform VARCHAR(50) NOT NULL,
    bde_brand VARCHAR(50) NOT NULL,
    bde_model VARCHAR(50) NOT NULL,
    bde_usr_id_create INTEGER DEFAULT NULL,
    bde_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bde_usr_id_change INTEGER DEFAULT NULL,
    bde_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_MYSQL_BILL_DEVICES_HIST = '
    deh_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bde_id INT UNSIGNED NOT NULL,
    bde_device_id VARCHAR(100) NOT NULL,
    bde_usr_id INT UNSIGNED NULL,
    bde_is_active TINYINT(1) NOT NULL DEFAULT 0,
    bde_active_date DATETIME NULL,
    bde_api_key VARCHAR(50) NULL,
    bde_platform VARCHAR(50) NOT NULL,
    bde_brand VARCHAR(50) NOT NULL,
    bde_model VARCHAR(50) NOT NULL,
    bde_usr_id_create INT UNSIGNED DEFAULT NULL,
    bde_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    bde_usr_id_change INT UNSIGNED DEFAULT NULL,
    bde_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    deh_action VARCHAR(20) NOT NULL,
    deh_usr_id INT UNSIGNED DEFAULT NULL,
    deh_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (deh_id)
    ';

    private const TABLE_DEFINITION_PGSQL_BILL_DEVICES_HIST = '
    deh_id SERIAL PRIMARY KEY,
    bde_id INTEGER NOT NULL,
    bde_device_id VARCHAR(100) NOT NULL,
    bde_usr_id INTEGER NULL,
    bde_is_active INTEGER NOT NULL DEFAULT 0,
    bde_active_date TIMESTAMP NULL,
    bde_api_key VARCHAR(50) NULL,
    bde_platform VARCHAR(50) NOT NULL,
    bde_brand VARCHAR(50) NOT NULL,
    bde_model VARCHAR(50) NOT NULL,
    bde_usr_id_create INTEGER DEFAULT NULL,
    bde_timestamp_create TIMESTAMP DEFAULT NULL,
    bde_usr_id_change INTEGER DEFAULT NULL,
    bde_timestamp_change TIMESTAMP DEFAULT NULL,
    deh_action VARCHAR(20) NOT NULL,
    deh_usr_id INTEGER DEFAULT NULL,
    deh_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';

    private const INVOICES_UNIQUE_INDEX_NUMBER = '
    CREATE UNIQUE INDEX ' . TABLE_PREFIX . '_idx_biv_number ON ' . TBL_BL_INVOICES . ' (biv_number)
    ';

    private const INVOICES_UNIQUE_INDEX_NUMBER_INDEX = '
    CREATE UNIQUE INDEX ' . TABLE_PREFIX . '_idx_biv_number_index ON ' . TBL_BL_INVOICES . ' (biv_number_index)
    ';

    private const INVOICES_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_biv_usr           FOREIGN KEY (biv_usr_id)          REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_biv_usr_create    FOREIGN KEY (biv_usr_id_create)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_biv_usr_change    FOREIGN KEY (biv_usr_id_change)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const ITEMS_INDEXES = '
    CREATE INDEX ' . TABLE_PREFIX . '_idx_bii_inv_id ON ' . TBL_BL_INVOICE_ITEMS . ' (bii_inv_id)
    ';

    private const ITEMS_INDEX_CHG_ID = '
    CREATE INDEX ' . TABLE_PREFIX . '_idx_bii_chg_id ON ' . TBL_BL_INVOICE_ITEMS . ' (bii_chg_id)
    ';

    private const ITEMS_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bii_inv          FOREIGN KEY (bii_inv_id)        REFERENCES ' . TBL_BL_INVOICES . ' (biv_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bii_usr_create   FOREIGN KEY (bii_usr_id_create)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bii_usr_change   FOREIGN KEY (bii_usr_id_change)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const PAYMENTS_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bpa_usr           FOREIGN KEY (bpa_usr_id)          REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bpa_usr_create    FOREIGN KEY (bpa_usr_id_create)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bpa_usr_change    FOREIGN KEY (bpa_usr_id_change)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const PAYMENT_ITEMS_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bpi_payment          FOREIGN KEY (bpi_payment_id)        REFERENCES ' . TBL_BL_PAYMENTS . ' (bpa_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bpi_usr_create   FOREIGN KEY (bpi_usr_id_create)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bpi_usr_change   FOREIGN KEY (bpi_usr_id_change)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const PG_PAYMENTS_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_btr_usr           FOREIGN KEY (btr_usr_id)          REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_btr_usr_create    FOREIGN KEY (btr_usr_id_create)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_btr_usr_change    FOREIGN KEY (btr_usr_id_change)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const TRANS_ITEMS_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bti_pg_payment          FOREIGN KEY (bti_pg_payment_id)        REFERENCES ' . TBL_BL_TRANS . ' (btr_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bti_usr_create   FOREIGN KEY (bti_usr_id_create)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_bti_usr_change   FOREIGN KEY (bti_usr_id_change)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const PAYMENT_ITEMS_INDEXES = '
    CREATE INDEX ' . TABLE_PREFIX . '_idx_bpi_payment_id ON ' . TBL_BL_PAYMENT_ITEMS . ' (bpi_payment_id)
    ';

    private const TRANS_ITEMS_INDEXES = '
    CREATE INDEX ' . TABLE_PREFIX . '_idx_bti_pg_payment_id ON ' . TBL_BL_TRANS_ITEMS . ' (bti_pg_payment_id)
    ';

    public function init(): void
    {
        $this->createTablesIfNotExist();
        $this->applySchemaUpgrades();
    }

    private function applySchemaUpgrades(): void
    {
        global $gDb, $gDbType;

        if (!tableExistsBILL(TBL_BL_INVOICE_ITEMS)) {
            return;
    }

        // Add charge-id column to invoice items and history tables (dev schema upgrades; no backwards-compat guarantees).
        $this->addColumnIfNotExists(
            TBL_BL_INVOICE_ITEMS,
            'bii_chg_id',
            'INT UNSIGNED NOT NULL DEFAULT 0',
            'INTEGER NOT NULL DEFAULT 0'
        );

        if (tableExistsBILL(TBL_BL_INVOICE_ITEMS_HIST)) {
            $this->addColumnIfNotExists(
        TBL_BL_INVOICE_ITEMS_HIST,
        'bii_chg_id',
        'INT UNSIGNED NOT NULL DEFAULT 0',
        'INTEGER NOT NULL DEFAULT 0'
            );
    }

        // Index for faster overlap checks (create if missing).
        $this->createIndexIfNotExist(TBL_BL_INVOICE_ITEMS, self::ITEMS_INDEX_CHG_ID);

        // Best-effort backfill: map existing item names to charge ids.
        if (tableExistsBILL(TBL_BL_CHARGES) && columnExistsBILL(TBL_BL_INVOICE_ITEMS, 'bii_name') && columnExistsBILL(TBL_BL_CHARGES, 'bch_name')) {
            if ($gDbType === 'pgsql') {
                $gDb->query(
                'UPDATE ' . TBL_BL_INVOICE_ITEMS . ' it '
                . 'SET bii_chg_id = c.bch_id '
                . 'FROM ' . TBL_BL_CHARGES . ' c '
                . 'WHERE (it.bii_chg_id IS NULL OR it.bii_chg_id = 0) '
                . 'AND c.bch_name = it.bii_name'
                );
            } else {
                $gDb->query(
                'UPDATE ' . TBL_BL_INVOICE_ITEMS . ' it '
                . 'INNER JOIN ' . TBL_BL_CHARGES . ' c ON c.bch_name = it.bii_name '
                . 'SET it.bii_chg_id = c.bch_id '
                . 'WHERE it.bii_chg_id IS NULL OR it.bii_chg_id = 0'
                );
            }
    }
    }

    private function addColumnIfNotExists(string $tableName, string $columnName, string $mysqlDefinition, string $pgsqlDefinition): void
    {
        global $gDb, $gDbType;

        if (!tableExistsBILL($tableName)) {
            return;
    }
        if (columnExistsBILL($tableName, $columnName)) {
            return;
    }

        $definition = ($gDbType === 'pgsql') ? $pgsqlDefinition : $mysqlDefinition;
        $gDb->query('ALTER TABLE ' . $tableName . ' ADD COLUMN ' . $columnName . ' ' . $definition);
    }

    public function uninstall(): void
    {
        $tables = array(
            TBL_BL_CHARGES,
            TBL_BL_CHARGES_HIST,
            TBL_BL_PAYMENT_ITEMS,
            TBL_BL_PAYMENT_ITEMS_HIST,
            TBL_BL_PAYMENTS,
            TBL_BL_PAYMENTS_HIST,
            TBL_BL_INVOICE_ITEMS,
            TBL_BL_INVOICE_ITEMS_HIST,
            TBL_BL_INVOICES,
            TBL_BL_INVOICES_HIST,
            TBL_BL_TRANS,
            TBL_BL_TRANS_ITEMS,
            TBL_BL_DEVICES,
            TBL_BL_DEVICES_HIST
        );

        foreach ($tables as $table) {
            $this->dropTableIfExists($table);
    }
    }

    private function createTablesIfNotExist(): void
    {
        global $gDbType;

        switch ($gDbType) {
            case 'pgsql':
        $this->createTableIfNotExist(TBL_BL_INVOICES, self::TABLE_DEFINITION_PGSQL_BILL_INVOICES);
        $this->createUniqueIndexIfNotExist(TBL_BL_INVOICES, self::INVOICES_UNIQUE_INDEX_NUMBER);
        $this->createUniqueIndexIfNotExist(TBL_BL_INVOICES, self::INVOICES_UNIQUE_INDEX_NUMBER_INDEX);
        $this->createConstraintsIfNotExist(TBL_BL_INVOICES, self::INVOICES_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_INVOICES_HIST, self::TABLE_DEFINITION_PGSQL_BILL_INVOICES_HIST);

        $this->createTableIfNotExist(TBL_BL_INVOICE_ITEMS, self::TABLE_DEFINITION_PGSQL_BILL_INVOICE_ITEMS);
        $this->createIndexIfNotExist(TBL_BL_INVOICE_ITEMS, self::ITEMS_INDEXES);
        $this->createConstraintsIfNotExist(TBL_BL_INVOICE_ITEMS, self::ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_INVOICE_ITEMS_HIST, self::TABLE_DEFINITION_PGSQL_BILL_INVOICE_ITEMS_HIST);

        $this->createTableIfNotExist(TBL_BL_PAYMENTS, self::TABLE_DEFINITION_PGSQL_BILL_PAYMENTS);
        $this->createConstraintsIfNotExist(TBL_BL_PAYMENTS, self::PAYMENTS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_PAYMENTS_HIST, self::TABLE_DEFINITION_PGSQL_BILL_PAYMENTS_HIST);

        $this->createTableIfNotExist(TBL_BL_PAYMENT_ITEMS, self::TABLE_DEFINITION_PGSQL_BILL_PAYMENT_ITEMS);
        $this->createIndexIfNotExist(TBL_BL_PAYMENT_ITEMS, self::PAYMENT_ITEMS_INDEXES);
        $this->createConstraintsIfNotExist(TBL_BL_PAYMENT_ITEMS, self::PAYMENT_ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_PAYMENT_ITEMS_HIST, self::TABLE_DEFINITION_PGSQL_BILL_PAYMENT_ITEMS_HIST);

        $this->createTableIfNotExist(TBL_BL_TRANS, self::TABLE_DEFINITION_PGSQL_BILL_TRANS);
        $this->createConstraintsIfNotExist(TBL_BL_TRANS, self::PG_PAYMENTS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_TRANS_ITEMS, self::TABLE_DEFINITION_PGSQL_BILL_TRANS_ITEMS);
        $this->createIndexIfNotExist(TBL_BL_TRANS_ITEMS, self::TRANS_ITEMS_INDEXES);
        $this->createConstraintsIfNotExist(TBL_BL_TRANS_ITEMS, self::TRANS_ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_CHARGES, self::TABLE_DEFINITION_PGSQL_BILL_CHARGES);
        $this->createTableIfNotExist(TBL_BL_CHARGES_HIST, self::TABLE_DEFINITION_PGSQL_BILL_CHARGES_HIST);

        $this->createTableIfNotExist(TBL_BL_DEVICES, self::TABLE_DEFINITION_PGSQL_BILL_DEVICES);
        $this->createTableIfNotExist(TBL_BL_DEVICES_HIST, self::TABLE_DEFINITION_PGSQL_BILL_DEVICES_HIST);
        break;

            case 'mysql':
            default:
        $this->createTableIfNotExist(TBL_BL_INVOICES, self::TABLE_DEFINITION_MYSQL_BILL_INVOICES);
        $this->createUniqueIndexIfNotExist(TBL_BL_INVOICES, self::INVOICES_UNIQUE_INDEX_NUMBER);
        $this->createUniqueIndexIfNotExist(TBL_BL_INVOICES, self::INVOICES_UNIQUE_INDEX_NUMBER_INDEX);
        $this->createConstraintsIfNotExist(TBL_BL_INVOICES, self::INVOICES_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_INVOICES_HIST, self::TABLE_DEFINITION_MYSQL_BILL_INVOICES_HIST);

        $this->createTableIfNotExist(TBL_BL_INVOICE_ITEMS, self::TABLE_DEFINITION_MYSQL_BILL_INVOICE_ITEMS);
        $this->createIndexIfNotExist(TBL_BL_INVOICE_ITEMS, self::ITEMS_INDEXES);
        $this->createConstraintsIfNotExist(TBL_BL_INVOICE_ITEMS, self::ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_INVOICE_ITEMS_HIST, self::TABLE_DEFINITION_MYSQL_BILL_INVOICE_ITEMS_HIST);

        $this->createTableIfNotExist(TBL_BL_PAYMENTS, self::TABLE_DEFINITION_MYSQL_BILL_PAYMENTS);
        $this->createConstraintsIfNotExist(TBL_BL_PAYMENTS, self::PAYMENTS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_PAYMENTS_HIST, self::TABLE_DEFINITION_MYSQL_BILL_PAYMENTS_HIST);

        $this->createTableIfNotExist(TBL_BL_PAYMENT_ITEMS, self::TABLE_DEFINITION_MYSQL_BILL_PAYMENT_ITEMS);
        $this->createIndexIfNotExist(TBL_BL_PAYMENT_ITEMS, self::PAYMENT_ITEMS_INDEXES);
        $this->createConstraintsIfNotExist(TBL_BL_PAYMENT_ITEMS, self::PAYMENT_ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_PAYMENT_ITEMS_HIST, self::TABLE_DEFINITION_MYSQL_BILL_PAYMENT_ITEMS_HIST);

        $this->createTableIfNotExist(TBL_BL_TRANS, self::TABLE_DEFINITION_MYSQL_BILL_TRANS);
        $this->createConstraintsIfNotExist(TBL_BL_TRANS, self::PG_PAYMENTS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_TRANS_ITEMS, self::TABLE_DEFINITION_MYSQL_BILL_TRANS_ITEMS);
        $this->createIndexIfNotExist(TBL_BL_TRANS_ITEMS, self::TRANS_ITEMS_INDEXES);
        $this->createConstraintsIfNotExist(TBL_BL_TRANS_ITEMS, self::TRANS_ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_BL_CHARGES, self::TABLE_DEFINITION_MYSQL_BILL_CHARGES);
        $this->createTableIfNotExist(TBL_BL_CHARGES_HIST, self::TABLE_DEFINITION_MYSQL_BILL_CHARGES_HIST);

        $this->createTableIfNotExist(TBL_BL_DEVICES, self::TABLE_DEFINITION_MYSQL_BILL_DEVICES);
        $this->createTableIfNotExist(TBL_BL_DEVICES_HIST, self::TABLE_DEFINITION_MYSQL_BILL_DEVICES_HIST);
    }
    }

    private function createTableIfNotExist(string $tableName, string $tableDefinition): void
    {
        global $gDb, $gDbType;

        if (!tableExistsBILL($tableName)) {
            if ($gDbType === 'pgsql') {
                $sql = 'CREATE TABLE ' . $tableName . ' (' . $tableDefinition . ');';
            } else {
                $sql = 'CREATE TABLE ' . $tableName . ' (' . $tableDefinition . ') ENGINE = InnoDB DEFAULT CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;';
            }

            $gDb->query($sql);
    }
    }

    private function createUniqueIndexIfNotExist(string $tableName, string $indexDefinition): void
    {
        global $gDb;
        $indexName = '';

        if (preg_match('/CREATE UNIQUE INDEX (\S+) ON/', $indexDefinition, $matches)) {
            $indexName = $matches[1];
    }

        if ($indexName !== '' && !indexExistsBILL($tableName, $indexName)) {
            $gDb->query($indexDefinition);
    }
    }

    private function createIndexIfNotExist(string $tableName, string $indexDefinition): void
    {
        global $gDb;
        $indexName = '';

        if (preg_match('/CREATE INDEX (\S+) ON/', $indexDefinition, $matches)) {
            $indexName = $matches[1];
    }

        if ($indexName !== '' && !indexExistsBILL($tableName, $indexName)) {
            $gDb->query($indexDefinition);
    }
    }

    private function createConstraintsIfNotExist(string $tableName, string $constraintsDefinition): void
    {
        global $gDb;
        $constraints = array_filter(array_map('trim', explode(',', $constraintsDefinition)));

        foreach ($constraints as $constraint) {
            if (preg_match('/ADD CONSTRAINT (\S+) FOREIGN KEY/', $constraint, $matches)) {
                $name = $matches[1];

                if (!constraintExistsBILL($tableName, $name)) {
                    $gDb->query('ALTER TABLE ' . $tableName . ' ' . $constraint);
    }
            }
    }
    }

    private function dropTableIfExists(string $tableName): void
    {
        global $gDb;

        if (tableExistsBILL($tableName)) {
            $gDb->query('DROP TABLE ' . $tableName);
    }
    }
}
