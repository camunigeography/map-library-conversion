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
		//# End if deleted
		//if ($record['LOCATION'] == 'DELETED') {return false;}
		
		# Start an array of fields
		$this->fields = array ();
		
		# ISBN
		$this->marc_generateLeader ();
		
		# ISBN
		$this->marc_generate007 ();
		
		# ISBN
		$this->marc_generate008 ();
		
		//# ISBN
		//$this->marc_generateIsbn ($record['ISBN']);
		
		# Author
		$this->marc_generateAuthors ($record['Author']);
		
		# Title
		$this->marc_generate245 ($record['Title'], $record['Author']);
		
		# Edition
		$this->marc_generateEdition ($record['Variant Title']);
		
		# Edition
		$this->marc_generateScale ($record['Scale']);
		
		# Publication
		$this->marc_generatePublication ($record['Author'], /* place= */ false, $record['Year']);
		
		# Physical description
		$this->marc_generatePhysicalDescription ($record['No of items']);
		
		# Type
		$this->marc_generateType ($record['Type']);
		
		# Note
		$notes = array ($record['Notes 1'], $record['Notes 2'], $record['Notes 3']);
		$this->marc_generateNotes ($notes);
		
		# Publication
		$this->marc_generatePlace ($record['Area']);
		
		# Location
		$this->marc_generateLocation ($record['Drawer'], $record['Number']);
		
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
	
	
	# Leader
	private function marc_generateLeader ()
	{
		# Register the result
		$this->fields['LDR'][0] = array (
			'' => str_repeat ('x', 40) . ' TODO',
		);
	}
	
	
	# 007 field
	private function marc_generate007 ()
	{
		# Register the result
		$this->fields['007'][0] = array (
			'a' => 'ta',
		);
	}
	
	
	# 008 field
	private function marc_generate008 ()
	{
		# Register the result
		$this->fields['008'][0] = array (
			'' => str_repeat ('x', 40) . ' TODO',
		);
	}
	
	
	# ISBN
	private function marc_generateIsbn ($isbn)
	{
		# End if none
		if ($isbn == 'no' || $isbn == '') {return false;}
		
		# Replace spaces and dashes
		$isbn = str_replace (array (' ', '-'), '', $isbn);
		
		# Register the result
		$this->fields['020'][0] = array (
			'a' => $isbn,
		);
	}
	
	
	# Authors
	private function marc_generateAuthors ($authors)
	{
		# Split by and
		$authors = explode (' and ', $authors);
		
		# Add each author
		foreach ($authors as $index => $author) {
			$author = trim ($author);
			
			# Determine the field to use
			$field = ($index == 0 ? '100' : '700');
			
			# Split the author components
			// TODO
			
			# Register the field
			$this->fields[$field][] = array (
				'_' => '1#',
				'a' => $this->dotEnd ($author),
			);
		}
	}
	
	
	# Title
	private function marc_generate245 ($title, $author)
	{
		# Deal with Remainder of title
		$remainder = false;
		if (substr_count ($title, ':')) {
			list ($title, $remainder) = explode (':', $title, 2);
			$title = trim ($title);
			$remainder = trim ($remainder);
		}
		
		# Register the result
		$this->fields['245'][0] = array (
			'a' => 'Map of ' . $this->reformatWords ($title) . ($remainder ? ':' : ' /'),
		);
		if ($remainder) {
			$this->fields['245'][0]['b'] = $remainder . ' /';
		}
		
		# Author
		$this->fields['245'][0]['c'] = $this->dotEnd ($author);
	}
	
	
	# Edition
	private function marc_generateEdition ($edition)
	{
		# End if none
		if (!strlen ($edition)) {return false;}
		
		# Register the result
		$this->fields['250'][0] = array (
			'a' => $edition . ' ed.',
		);
	}
	
	
	# Scale
	private function marc_generateScale ($scale)
	{
		# End if none
		if (!strlen ($scale)) {return false;}
		
		# Register the result
		$this->fields['255'][0] = array (
			'a' => ucfirst (($scale != 'no scale' ? 'Scale ' : '') . $scale),
		);
	}
	
	
	# Publication
	private function marc_generatePublication ($publisher, $place, $date)
	{
		# Register the result
		$this->fields['260'][0] = array (
			'a' => $publisher,
			'b' => $place,
			'c' => $date,
		);
	}
	
	
	# Physical description
	private function marc_generatePhysicalDescription ($number)
	{
		# Register the result
		$this->fields['300'][] = array (
			'a' => ($number == 1 ? '1 map' : $number . ' maps'),
		);
	}
	
	
	# Publication
	private function marc_generateType ($type)
	{
		# End if none
		if (!strlen ($type)) {return false;}
		
		# Register the result
		$this->fields['500'][] = array (
			'a' => 'Type: ' . $type,
		);
	}
	
	
	# Note
	private function marc_generateNotes ($notes)
	{
		# Register the result
		foreach ($notes as $note) {
			$note = trim ($note);
			if (strlen ($note)) {
				$this->fields['500'][] = array (
					'a' => ucfirst (strtolower ($note)),
				);
			}
		}
	}
	
	
	# Place (of the map content)
	private function marc_generatePlace ($country)
	{
		# End if none
		if (!strlen ($country)) {return false;}
		
		# Register the result
		$this->fields['751'][0] = array (
			'a' => $this->reformatWords ($country),
		);
	}
	
	
	# Location
	private function marc_generateLocation ($drawer, $number)
	{
		# Register the result
		$this->fields['852'][0] = array (
			'2' => 'camdept',
			'b' => 'GEO',
			'c' => 'GEOG-MAP',
		);
		
		# Drawer and number
		$this->fields['852'][0]['d'] = "Drawer {$drawer} {$number}";
	}
}

?>
