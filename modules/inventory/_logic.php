<?php
/**
 * modules/inventory/_logic.php
 * Shared helpers for the inventory module.
 */

/**
 * Inventory categories (blueprint Module 8).
 *
 * @return string[]
 */
function inventory_categories(): array
{
    return ['Fertilizer', 'Chemical', 'Tools', 'PPE', 'Spare Parts', 'Fuel', 'General Supplies'];
}
