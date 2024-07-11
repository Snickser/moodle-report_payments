<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Payment entity class implementation.
 *
 * @package   report_payments
 * @copyright 2023 Medical Access Uganda Limited
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_payments\reportbuilder\local\entities;

use core_reportbuilder\local\filters\{date, duration, number, select, text, autocomplete};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\{column, filter};
use lang_string;

/**
 * Payment entity class implementation.
 *
 * @package   report_payments
 * @copyright 2023 Medical Access Uganda Limited
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment extends base {
    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['payments' => 'pa'];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('payments');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        // All the filters defined by the entity can also be used as conditions.
        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['payments'];
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $tablealias = $this->get_table_alias('payments');
        $name = $this->get_entity_name();

        // Payment id.
        $columns[] = (new column('id', new lang_string('paymentid', 'report_payments'), $name))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.id")
            ->set_is_sortable(true);

global $DB;
$dbman = $DB->get_manager();

$str = "LEFT JOIN (select 0 paymentid, 0 courseid, 0 success, 0 recurrent";
if($dbman->table_exists('paygw_robokassa')){
    $str .= " union select paymentid,courseid,success,recurrent from mdl_paygw_robokassa ";
}
if($dbman->table_exists('paygw_yookassa')){
    $str .= " union select paymentid,courseid,success,recurrent from mdl_paygw_yookassa ";
}
if($dbman->table_exists('paygw_payanyway')){
    $str .= " union select paymentid,courseid,success,0 recurrent from mdl_paygw_payanyway ";
}
if($dbman->table_exists('paygw_cryptocloud')){
    $str .= " union select paymentid,courseid,success,0 recurrent from mdl_paygw_cryptocloud ";
}
$str .= ") rb ON rb.paymentid={$tablealias}.id";

        // Component column.
        $columns[] = (new column('course', new lang_string('course'), $name))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("rb.courseid")
            ->set_is_sortable(true);

        // Payment recurrent.
        $columns[] = (new column('recurrent', new lang_string('recurrent','report_payments'), $name))
            ->add_joins($this->get_joins())
            ->add_join($str)
            ->set_type(column::TYPE_INTEGER)
            ->add_fields("rb.recurrent, {$tablealias}.id")
            ->add_attributes(['class' => 'text-center'])
            ->set_is_sortable(true)
            ->add_callback(function (?int $value, \stdClass $row): string {
             if($value>0){ 
                return '<b style="color: red;">' . new lang_string('yes'). '</b>' .
                '<br>'.'<a href="?cancel=' . $row->id .'&sesskey='. sesskey() . '">' . new lang_string('cancel') . '</a>';
             }
             else return false;
            });

        // Payment success.
        $columns[] = (new column('success', new lang_string('status'), $name))
            ->add_joins($this->get_joins())
            ->add_join($str)
            ->set_type(column::TYPE_INTEGER)
            ->add_field("rb.success")
            ->add_attributes(['class' => 'text-center'])
            ->set_is_sortable(true)
            ->add_callback(function (?int $value): string {
            !isset($value) ? $value=-1 : false;
            switch ($value) {
        	case 0:
        	    return '<div style="color: red;">' . new lang_string('unfinished') . '</div>';
        	    break;
        	case 1:
        	    return '<b style="color: green;">' . new lang_string('success') . '</b>';
        	    break;
        	case 2:
        	    return '<b style="color: blue;">' . new lang_string('password') . '</b>';
        	    break;
        	case 3:
        	    return new lang_string('ok');
        	    break;
        	default:
        	    return new lang_string('no');
        	}
            });

        // Accountid column.
        $columns[] = (new column('accountid', new lang_string('name', 'report_payments'), $name))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {payment_accounts} pac ON {$tablealias}.accountid = pac.id")
            ->set_type(column::TYPE_TEXT)
            ->add_field("pac.name")
            ->set_is_sortable(true);

        // Component column.
        $columns[] = (new column('component', new lang_string('plugin'), $name))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.component")
            ->set_is_sortable(true);

        // Gateway column.
        $columns[] = (new column('gateway', new lang_string('type_paygw', 'plugin'), $name))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.gateway")
            ->set_is_sortable(true);

        // Amount column.
        $columns[] = (new column('amount', new lang_string('cost', 'report_payments'), $name))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_fields("{$tablealias}.amount, {$tablealias}.currency")
//            ->set_is_sortable(true)
            ->add_attributes(['class' => 'text-right'])
            ->add_callback(function (?int $value, \stdClass $row): string {
                  return \core_payment\helper::get_cost_as_string($row->amount, $row->currency);
//                return ($value === '') ? '0' : number_format(floatval($value), 2, '.', '');
            });

        // Currency column.
        $columns[] = (new column('currency', new lang_string('currency'), $name))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.currency")
            ->set_is_sortable(true);
//            ->add_callback(function (?string $value): string {
//                  return new lang_string($value, 'currencies');
//            });

        // Date column.
        $columns[] = (new column('timecreated', new lang_string('date'), $name))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timemodified")
            ->set_is_sortable(true)
//            ->add_attributes(['class' => 'text-right'])
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshortaccurate', 'core_langconfig'));
        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {

        $tablealias = $this->get_table_alias('payments');
        $name = $this->get_entity_name();

        $ownermethod = static function (): array {
            global $DB;
            return $DB->get_records_menu('payment_accounts', ['enabled' => true]);
        };

        // Name filter.
        $filters[] = (new filter(select::class, 'accountid', new lang_string('name'), $name, "{$tablealias}.accountid"))
            ->add_joins($this->get_joins())
            ->set_options_callback($ownermethod);

        // Component filter.
        $filters[] = (new filter(text::class, 'component', new lang_string('plugin'), $name, "{$tablealias}.component"))
            ->add_joins($this->get_joins());

        // Gateway filter.
        $filters[] = (new filter(text::class, 'gateway', new lang_string('type_paygw', 'plugin'), $name, "{$tablealias}.gateway"))
            ->add_joins($this->get_joins());

        // Currency filter.
        $filters[] = (new filter(text::class, 'currency', new lang_string('currency'), $name, "{$tablealias}.currency"))
            ->add_joins($this->get_joins());

        // Amount filter.
        $filters[] = (new filter(number::class, 'amount', new lang_string('cost'), $name, "{$tablealias}.amount"))
            ->add_joins($this->get_joins());

        // Recurrent filter.
        $filters[] = (new filter(number::class, 'recurrent', new lang_string('recurrent','report_payments'), $name, "{$tablealias}.recurrent"))
            ->add_joins($this->get_joins());

        // Date filter.
        $filters[] = (new filter(date::class, 'timecreated', new lang_string('date'), $name, "{$tablealias}.timecreated"))
            ->add_joins($this->get_joins())
            ->set_limited_operators([date::DATE_ANY, date::DATE_RANGE, date::DATE_PREVIOUS, date::DATE_CURRENT]);

        return $filters;
    }
}
