<?php

# Class to manage Map library catalogue conversion
require_once ('frontControllerApplication.php');
class mapLibraryConversion extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'applicationName' => 'Map library catalogue conversion',
			'useDatabase' => false,
			'dataFile' => './data.csv',
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function to assign supported actions
	public function actions ()
	{
		# Define available tasks
		$actions = array (
			'home' => array (
				'description' => false,
				'url' => '',
				'tab' => 'Home',
				'icon' => 'house',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	
	# Additional processing
	public function main ()
	{
		
	}
	
	
	
	# Home page
	public function home ()
	{
		
		# Load the data
		$data = $this->loadData ();
	}
	
	
	# Function to load the CSV data
	private function loadData ()
	{
		# Load the data
		require_once ('csv.php');
		$data = csv::getData ($this->settings['dataFile'], $stripKey = false, $hasNoKeys = true, $allowsRowsWithEmptyFirstColumn = true);
		
		# Trim cells and fix double-spaces
		foreach ($data as $index => $row) {
			foreach ($row as $key => $value) {
				$data[$index][$key] = trim (str_replace ('  ', ' ', $value));
			}
		}
		
		# Return the data
		return $data;
	}
}

?>
