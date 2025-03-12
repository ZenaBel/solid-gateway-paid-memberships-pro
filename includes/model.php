<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class PMProGateway_Solid_Product_Model
{
    public static function get_product_mapping_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pmpro_solid_product_mappings';
    }

    public static function get_all_product_mappings()
    {
        global $wpdb;
        $table_name = self::get_product_mapping_table_name();
        $sql = "SELECT * FROM $table_name";
        return $wpdb->get_results($sql);
    }

    public static function get_product_mapping_by_product_id($product_id)
    {
        global $wpdb;
        $table_name = self::get_product_mapping_table_name();
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE product_id = %d", $product_id);
        return $wpdb->get_row($sql);
    }

    public static function get_product_mapping_by_uuid($uuid)
    {
        global $wpdb;
        $table_name = self::get_product_mapping_table_name();
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE uuid = %s", $uuid);
        return $wpdb->get_row($sql);
    }

    public static function create_product_mapping($product_id, $uuid, $is_editable = 1)
    {
        if (!self::get_product_mapping_by_product_id($product_id)) {
            global $wpdb;
            $table_name = self::get_product_mapping_table_name();
            $result = $wpdb->insert($table_name, [
                'product_id' => $product_id,
                'uuid' => $uuid,
                'is_editable' => $is_editable,
            ]);
            if ($result === false) {
                error_log('Failed to insert product mapping: ' . $wpdb->last_error);
            }

            return $result;
        }
        return false;
    }

    public static function update_product_mapping($product_id, $new_uuid, $is_editable = null)
    {
        global $wpdb;
        $table_name = self::get_product_mapping_table_name();
        $data = ['uuid' => $new_uuid];
        $where = ['product_id' => $product_id];

        if ($is_editable !== null) {
            $data['is_editable'] = $is_editable;
        }

        $wpdb->update($table_name, $data, $where);
    }

    public static function delete_product_mapping_by_product_id($product_id)
    {
        global $wpdb;
        $table_name = self::get_product_mapping_table_name();
        $wpdb->delete($table_name, ['product_id' => $product_id]);
    }

    public static function delete_product_mapping_by_uuid($uuid)
    {
        global $wpdb;
        $table_name = self::get_product_mapping_table_name();
        $wpdb->delete($table_name, ['uuid' => $uuid]);
    }

    public static function set_is_editable($product_id, $is_editable)
    {
        global $wpdb;
        $table_name = self::get_product_mapping_table_name();
        $wpdb->update($table_name, ['is_editable' => $is_editable], ['product_id' => $product_id]);
    }

    public static function get_is_editable($product_id): ?string
    {
        global $wpdb;
        $table_name = self::get_product_mapping_table_name();
        $sql = $wpdb->prepare("SELECT is_editable FROM $table_name WHERE product_id = %d", $product_id);
        return $wpdb->get_var($sql);
    }

    public static function create_table()
    {
        global $wpdb;
        $table_name = self::get_product_mapping_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            uuid varchar(255) NOT NULL,
            is_editable tinyint(1) DEFAULT 1 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
