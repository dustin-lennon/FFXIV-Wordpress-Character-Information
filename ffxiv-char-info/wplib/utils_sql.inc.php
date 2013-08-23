<?php
/**
 * Wordpress SQL Utility Library
 * 
 * A group of functions to make it easier to work with mysql database SQL statements.
 * 
 * This code is very much in alpha phase, and should not be distributed with plugins 
 * other than by Dan Harrison. 
 * 
 * @author Dan Harrison of WP Doctors (http://www.wpdoctors.co.uk)
 *
 * Version History
 * 
 * V0.01 				 - Initial version released.
 * V0.02 				 - Added ability for multiple columns in WHERE clause in arrayToSQLUpdate()
 * V0.03 				 - Added backticks to column names to escape column names which might be reserved words.
 * V0.04 - 30th Oct 2010 - Added function to check for data value in a table.
 *
 */

/**
 * Returns the correct SQL to INSERT the specified values as columnname => data into the specified
 * table, escaping all of the data values.
 * 
 * @param $tablename The name of the table to insert into.
 * @param $dataarray The list of values as columnname => data.
 * @return String Valid SQL to allow the specified values to be safely INSERTed into the database.
 */
if (!function_exists('arrayToSQLInsert')) { function arrayToSQLInsert($tablename, $dataarray)
{
	global $wpdb; 

	// Handle dodgy data
	if (!$tablename || !$dataarray || count($dataarray) == 0) {
		return false;
	}

	$SQL = "INSERT INTO $tablename (";

	// Insert Column Names
	$columnnames = array_keys($dataarray);
	foreach ($columnnames AS $column) {
		$SQL .= sprintf('`%s`, ', $column);
	}

	// Remove last comma to maintain valid SQL
	if (substr($SQL, -2) == ', ') {
		$SQL = substr($SQL, 0, strlen($SQL)-2);
	}

	$SQL .= ") VALUES (";

	// Now add values, escaping them all
	foreach ($dataarray AS $columnname => $datavalue) {
		$SQL .= "'" . $wpdb->escape($datavalue) . "', ";
	}

	// Remove last comma to maintain valid SQL
	if (substr($SQL, -2) == ', ') {
		$SQL = substr($SQL, 0, strlen($SQL)-2);
	}

	return $SQL . ")";
}}

/**
 * Returns the correctly formed SQL to UPDATE the specified values in the database 
 * using the <code>$wherecolumn</code> field to determine which field is used as part 
 * of the WHERE clause of the SQL statement. The fields and data are specified in an 
 * array mapping columnname => data.
 * 
 * @param $tablename The name of the table to UPDATE.
 * @param $dataarray The list of values as columnname => data.
 * @param $wherecolumn The column to use in the WHERE clause.  
 * @return String Valid SQL to allow the specified values to be safely UPDATEed in the database.
 */
if (!function_exists('arrayToSQLUpdate')) { function arrayToSQLUpdate($tablename, $dataarray, $wherecolumn)
{
	global $wpdb; 

	// Handle dodgy data
	if (!$tablename || !$dataarray || !$wherecolumn || count($dataarray) == 0) {
		return false;
	}

	$SQL = "UPDATE $tablename SET ";

	// Now add values, escaping them all
	foreach ($dataarray AS $columnname => $datavalue)
	{
		// Do all fields except column we're using on the WHERE part
		if ($columnname != $wherecolumn) {
			$SQL .= "`$columnname` = '" . $wpdb->escape($datavalue) . "', ";
		}
	}

	// Remove last comma to maintain valid SQL
	if (substr($SQL, -2) == ', ') {
		$SQL = substr($SQL, 0, strlen($SQL)-2);
	}

	// Now add the WHERE clause 
	// Have we got more than 1 item to add to WHERE clause?
	if (is_array($wherecolumn))
	{
		// Create list of fields/values in the WHERE clause
		$WHERE = '';
		for ($i = 0; $i < count($wherecolumn); $i++)
		{
			$WHERE .= sprintf("`%s` = '%s' AND ", $wherecolumn[$i], $wpdb->escape($dataarray[$wherecolumn[$i]]));
		}

		// Always going to have a final AND, so strip that off now
		$WHERE = substr($WHERE, 0, -4);
		$SQL .= " WHERE ". $WHERE;
	}

	// Nope, just a single item
	else {
		$SQL .= " WHERE `$wherecolumn` = '" . $wpdb->escape($dataarray[$wherecolumn]) . "'";
	}

	return $SQL;
}}

/**
 * Does a record exist for the specified table? Handles multiple fields for $field and $value 
 * if $field and $value are arrays.
 * 
 * @param String $table The name of the table to check.
 * @param String $field The field of the table to use as part of the search.
 * @param String $value The value of the field to use as part of the search.
 * @return Mixed The row of data if this row is found.
 */
if (!function_exists('doesRecordExistAlready')) { function doesRecordExistAlready($table, $field, $value)
{
	return getRecordDetails($table, $field, $value);
}}



/**
 * Get all of the details for the specified record.
 * 
 * @param String $table The name of the table to check.
 * @param String $field The field of the table to use as part of the search.
 * @param String $value The value of the field to use as part of the search.
 * @param String $returnType The type of data to return (ARRAY_A or OBJECT)
 * @return Mixed The row of data if this row is found.
 */
if (!function_exists('getRecordDetails')) { function getRecordDetails($table, $field, $value, $returnType = OBJECT)
{
	global $wpdb;
	$wpdb->show_errors();

	// We've got 2 arrays, so make sure we have the right number of elements.
	if (is_array($field) && is_array($value))
	{
		if (count($field) != count($value)) { 
			die('Error! Mismatched field count for checking record exists.');
		}

		// Create list of fields/values in the WHERE clause
		$WHERE = '';
		for ($i = 0; $i < count($field); $i++)
		{
			$WHERE .= sprintf("%s = '%s' AND ", $field[$i], $wpdb->escape($value[$i]));
		}

		// Always going to have a final AND, so strip that off now
		$WHERE = substr($WHERE, 0, -4);
		$SQL = sprintf("SELECT * FROM %s WHERE %s", $table, $WHERE);
	}

	// Just got a single pair of fields/values
	else {
		$SQL = sprintf("SELECT * FROM %s WHERE %s = '%s'", $table, $field, $wpdb->escape($value));	
	}

	return $wpdb->get_row($SQL, $returnType);
}}

?>