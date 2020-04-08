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
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
		# Load the data
		$data = $this->loadData ();
		
		# Create MARC records from the data
		$marc = $this->createMarcRecords ($data);
		
		var_dump ($marc);
		
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
	
	
	# Function to create MARC records from the data
	private function createMarcRecords ($data)
	{
		# Convert each record
		$marc = array ();
		foreach ($data as $record) {
			if ($marcRecord = $this->convertToMarc ($record)) {
				$marc[] = $marcRecord;
			}
		}
		
		# Compile to string
		$marc = implode ("\n\n", $marc);
		
		# Return the string
		return $marc;
	}
	
	
	# Convert to MARC
	private function convertToMarc ($record)
	{
		# Start an array of fields
		$this->fields = array ();
		
		# Implement each field
		// TODO
		
		# Assemble each line
		$lines = array ();
		foreach ($this->fields as $field => $instances) {
			foreach ($instances as $subfields) {
				
				# Determine indicators
				$indicators = '##';
				if (isSet ($subfields['_'])) {
					$indicators = $subfields['_'];
					unset ($subfields['_']);
				}
				
				# Add each subfields
				$tokens = array ();
				foreach ($subfields as $subfield => $value) {
					if (strlen ($value)) {
						$tokens[] = $this->doubleDagger . $subfield . $value;
					}
				}
				
				# Compile the line
				$lines[] = $field . ' ' . $indicators . ' ' . implode (' ', $tokens);
			}
		}
		
		# Compile lines
		$marc = "\n" . implode ("\n", $lines);
		
		# Return the result
		return $marc;
	}
	
	
	# Dot-end
	private function dotEnd ($string)
	{
		return $string . (substr ($string, -1) == '.' ? '' : '.');
	}
	
	
	# Helper function to reformat words
	private function reformatWords ($string)
	{
		# No change if any lower-case
		if (preg_match ('/[a-z]/', $string)) {return $string;}
		
		$string = mb_strtolower ($string);
		$string = ucwords ($string);
		return $string;
	}
}

?>
