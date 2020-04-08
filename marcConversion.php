<?php

# Class to handle MARC conversion
class marcConversion
{
	# Constructor
	public function __construct ()
	{
		// No action
	}
	
	
	
	# Convert to MARC
	public function convertToMarc ($record)
	{
		# Start an array of fields
		$this->fields = array ();
		
		# Convert each field
		$this->processFields ($record);
		
		//# Sort the fields, leaving the Leader at the start
		//ksort ($this->fields);
		
		# Define unicode symbols
		$doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
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
						$tokens[] = (strlen ($subfield) ? $doubleDagger . $subfield : '') . $value;		// Double-dagger omitted for LDR
					}
				}
				
				# Compile the line
				$lines[] = $field . ' ' . $indicators . ' ' . implode (' ', $tokens);
			}
		}
		
		# Compile lines
		$record = "\n" . implode ("\n", $lines);
		
		# Determine the length, in bytes, which is the first five characters of the 000 (Leader), padded
		$bytes = mb_strlen ($record);
		$bytes = str_pad ($bytes, 5, '0', STR_PAD_LEFT);
		$record = preg_replace ('/^LDR ## (_____)/m', "LDR ## {$bytes}", $record);
		
		# Return the result
		return $record;
	}
	
	
	# Function to convert each field
	private function processFields ($record)
	{
		//# End if deleted
		//if ($record['LOCATION'] == 'DELETED') {return false;}
		
		# ISBN
		$this->generateLeader ($record['Year']);
		
		# ISBN
		$this->generate007 ();
		
		# ISBN
		$this->generate008 ();
		
		//# ISBN
		//$this->generateIsbn ($record['ISBN']);
		
		# Author
		$this->generateAuthors ($record['Author']);
		
		# Title
		$this->generate245 ($record['Title'], $record['Author']);
		
		# Edition
		$this->generateEdition ($record['Variant Title']);
		
		# Edition
		$this->generateScale ($record['Scale']);
		
		# Publication
		$this->generatePublication ($record['Author'], /* place= */ false, $record['Year']);
		
		# Physical description
		$this->generatePhysicalDescription ($record['No of items']);
		
		# Type
		$this->generateType ($record['Type']);
		
		# Note
		$notes = array ($record['Notes 1'], $record['Notes 2'], $record['Notes 3']);
		$this->generateNotes ($notes);
		
		# Publication
		$this->generatePlace ($record['Area']);
		
		# Location
		$this->generateLocation ($record['Drawer'], $record['Number']);
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
	
	
	/* Fields */
	
	# Leader
	private function generateLeader ($year)
	{
		# Start the string
		$string = '';
		
		# Positions 00-04: "Computer-generated, five-character number equal to the length of the entire record, including itself and the record terminator. The number is right justified and unused positions contain zeros."
		$string .= '_____';		// Will be fixed-up later in post-processing, as at this point we do not know the length of the record
		
		# Position 05: One-character alphabetic code that indicates the relationship of the record to a file for file maintenance purposes.
		$string .= 'n';		// Indicates record is newly-input
		
		# Position 06: One-character alphabetic code used to define the characteristics and components of the record.
		$string .= 'e';		// Cartographic material
		
		# Position 07: Bibliographic level
		# Treat year range as serials, and single year (or no year) as item
		$string .= (substr_count ($year, '-') ? 's' : 'm');
		
		# Position 08: Type of control
		$string .= '#';
		
		# Position 09: Character coding scheme - Unicode
		$string .= 'a';
		
		# Position 10: Indicator count: Computer-generated number 2 that indicates the number of character positions used for indicators in a variable data field.
		$string .= '2';
		
		# Position 11: Subfield code count: Computer-generated number 2 that indicates the number of character positions used for each subfield code in a variable data field.
		$string .= '2';
		
		# Positions 12-16: Base address of data: Computer-generated, five-character numeric string that indicates the first character position of the first variable control field in a record.
		# "This is calculated and updated when the bib record is loaded into the Voyager database, so you if you're not able to calculate it at your end you could just set it to 00000."
		$string .= '00000';
		
		# Position 17: Encoding level: One-character alphanumeric code that indicates the fullness of the bibliographic information and/or content designation of the MARC record.
		$string .= '4';		// Core level
		
		# Position 18: Descriptive cataloguing form
		$string .= 'a';		// Denotes AACR2
		
		# Position 19: Multipart resource record level
		$string .= '#';		// Denotes not specified or not applicable
		
		# Position 20: Length of the length-of-field portion: Always contains a 4.
		$string .= '4';
		
		# Position 21: Length of the starting-character-position portion: Always contains a 5.
		$string .= '5';
		
		# Position 22: Length of the implementation-defined portion: Always contains a 0.
		$string .= '0';
		
		# Position 23: Undefined: Always contains a 0.
		$string .= '0';
		
		# Register the result
		$this->fields['LDR'][0] = array (
			'' => $string,
		);
	}
	
	
	# 007 field
	private function generate007 ()
	{
		# Register the result
		$this->fields['007'][0] = array (
			'a' => 'ta',
		);
	}
	
	
	# 008 field
	private function generate008 ()
	{
		# Register the result
		$this->fields['008'][0] = array (
			'' => str_repeat ('x', 40) . ' TODO',
		);
	}
	
	
	# ISBN
	private function generateIsbn ($isbn)
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
	private function generateAuthors ($authors)
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
	private function generate245 ($title, $author)
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
	private function generateEdition ($edition)
	{
		# End if none
		if (!strlen ($edition)) {return false;}
		
		# Register the result
		$this->fields['250'][0] = array (
			'a' => $edition . ' ed.',
		);
	}
	
	
	# Scale
	private function generateScale ($scale)
	{
		# End if none
		if (!strlen ($scale)) {return false;}
		
		# Register the result
		$this->fields['255'][0] = array (
			'a' => ucfirst (($scale != 'no scale' ? 'Scale ' : '') . $scale),
		);
	}
	
	
	# Publication
	private function generatePublication ($publisher, $place, $date)
	{
		# Register the result
		$this->fields['260'][0] = array (
			'a' => $publisher,
			'b' => $place,
			'c' => $date,
		);
	}
	
	
	# Physical description
	private function generatePhysicalDescription ($number)
	{
		# Register the result
		$this->fields['300'][] = array (
			'a' => ($number == 1 ? '1 map' : $number . ' maps'),
		);
	}
	
	
	# Publication
	private function generateType ($type)
	{
		# End if none
		if (!strlen ($type)) {return false;}
		
		# Register the result
		$this->fields['500'][] = array (
			'a' => 'Type: ' . $type,
		);
	}
	
	
	# Note
	private function generateNotes ($notes)
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
	private function generatePlace ($country)
	{
		# End if none
		if (!strlen ($country)) {return false;}
		
		# Register the result
		$this->fields['751'][0] = array (
			'a' => $this->reformatWords ($country),
		);
	}
	
	
	# Location
	private function generateLocation ($drawer, $number)
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
