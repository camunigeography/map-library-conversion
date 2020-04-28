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
				$lines[] = $field . ($indicators !== false ? ' ' . $indicators : '') . ' ' . implode ($tokens);
			}
		}
		
		# Compile lines
		$record = "\n" . implode ("\n", $lines);
		
		# Determine the length, in bytes, which is the first five characters of the 000 (Leader), padded
		$bytes = mb_strlen ($record);
		$bytes = str_pad ($bytes, 5, '0', STR_PAD_LEFT);
		$record = preg_replace ('/^LDR (_____)/m', "LDR {$bytes}", $record);
		
		# Return the result
		return $record;
	}
	
	
	# Function to convert each field
	private function processFields ($record)
	{
		//# End if deleted
		//if ($record['LOCATION'] == 'DELETED') {return false;}
		
		# Leader
		$this->generateLeader ($record['Year']);
		
		# 005
		$this->generate005 ();
		
		# ISBN
		$this->generate007 ($record['Title']);
		
		# 008 field
		$this->generate008 ($record['Year'], $record['Author']);
		
		//# ISBN
		//$this->generateIsbn ($record['ISBN']);
		
		# 040 field
		$this->generate040 ();
		
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
		
		# Note
		$notes = array ($record['Notes 1'], $record['Notes 2'], $record['Notes 3']);
		$this->generateNotes ($notes);
		
		# Type
		$this->generateType ($record['Type']);
		
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
			'_' => false,
			'' => $string,
		);
	}
	
	
	# 005 field
	private function generate005 ()
	{
		# Date and Time of Latest Transaction; see: https://www.loc.gov/marc/bibliographic/bd005.html
		$date = date ('YmdHis.0');
		
		# Register the result
		$this->fields['005'][0] = array (
			'_' => false,
			'' => $date,
		);
	}
	
	
	# 007 field - Physical Description Fixed Field; see: https://www.loc.gov/marc/bibliographic/bd007.html
	private function generate007 ($title)
	{
		# Set the value for Map
		# See: https://www.loc.gov/marc/bibliographic/bd007a.html
		
		# 00 - Category of material
		$string  = 'a';		// Map
		
		# 01 - Specific material designation
		if (substr_count (strtolower ($title), 'atlas')) {
			$string .= 'd';		// Atlas
		} else {
			$string .= 'j';		// Map
		}
		
		# 02 - Undefined
		$string .= '#';		// Undefined
		
		# 03 - Color
		$string .= '|';		// No attempt to code
		
		# 04 - Physical medium
		$string .= 'a';		// Paper
		
		# 05 - Type of reproduction
		$string .= '|';		// No attempt to code
		
		# 06 - Production/reproduction details
		$string .= '|';		// No attempt to code
		
		# 07 - Positive/negative aspect
		$string .= '|';		// No attempt to code
		
		# Register the result
		$this->fields['007'][0] = array (
			'_' => false,
			'' => $string,
		);
	}
	
	
	# 008 field
	private function generate008 ($year, $publisher)
	{
		# Start the string
		$string = '';
		
		# Positions 00-05: Date entered on system [format: yymmdd]
		$string .= date ('ymd');
		
		# Position 06: Type of date/Publication status
		# Positions 07-10 - Date 1
		# Positions 11-14 - Date 2
		# See: https://www.loc.gov/marc/bibliographic/bd008a.html
		switch (true) {
			# Date range, e.g. 1889-1951:
			case (preg_match ('/^([0-9]{4}) ?[-\/] ?([0-9]{4})$/', $year, $matches)):
				$status06 = 'd' . $matches[1] . $matches[2];
				break;
			# Date range with second as two digits, e.g. 1964-77:
			case (preg_match ('/^([0-9]{4}) ?[-\/] ?([0-9]{2})$/', $year, $matches)):
				$status06 = 'd' . $matches[1] . substr ($matches[1], 0, 2) . $matches[2];
				break;
			# Date range with second as one digit, e.g. 1940-2:
			case (preg_match ('/^([0-9]{4}) ?- ?([0-9]{1})$/', $year, $matches)):
				$status06 = 'd' . $matches[1] . substr ($matches[1], 0, 3) . $matches[2];
				break;
			# Single date, e.g. 1978
			case (preg_match ('/^([0-9]{4})$/', $year)):
				$status06 = 's' . $year . '####';
				break;
			# Single date with circa, e.g. c1942
			case (preg_match ('/^[c>]\.? ?([0-9]{4})$/', $year, $matches)):
				$status06 = 's' . $matches[1] . '####';
				break;
			# No date or undated
			case (in_array ($year, array ('undated', 'no date', 'unknown'))):
			case (!strlen ($year)):
				$status06 = 'n' . 'uuuu' . 'uuuu';
				break;
			# Catch other cases
			default:
				echo $year;
		}
		$string .= $status06;
		
		# Positions 15-17 - Place of publication, production, or execution
		# See: https://www.loc.gov/marc/countries/countries_code.html
		$string .= $this->getCountryCode ($publisher);
		
		# Positions 18-34 - Maps type section
		
		# Positions 18-21 - Relief
		$string .= '||||';	// No attempt to code
		
		# Positions 22-23 - Projection
		$string .= '||';	// No attempt to code
		
		# Position 24 - Undefined
		$string .= '#';
		
		# Position 25 - Type of cartographic material
		# Series vs single, based on whether there is a year range
		$string .= (substr_count ($year, '-') ? 'b' : 'a');
		
		# Positions 26-27 - Undefined
		$string .= '##';
		
		# Positions 28 - Government publication
		$governmentOrganisations = array (
			'Ordnance Survey',
			'Geological Survey',
			'War Office',
			'GSGS',
			'Ministry of Defence',
			'MoD',
			'Directorate of Overseas Surveys',
			'Directorate of Colonial Surveys',
			'Ministry of Agriculture',
			'National Institute of Oceanography, GB',
			'Hydrographic Office',
			'US Army',
			'National Geographic',
			'Department of Defence',
			'US Department',
			'USGS',
			'US Naval',
		);
		$governmentPublicationValue = '|';	// No attempt to code
		if (strlen ($publisher)) {
			foreach ($governmentOrganisations as $governmentOrganisation) {
				if (substr_count ($governmentOrganisation, $publisher)) {
					$governmentPublicationValue = 'f';
					break;
				}
			}
		}
		$string .= $governmentPublicationValue;
		
		# Position 29 - Form of item
		$string .= 'r';		// Assume 'Regular print reproduction'
		
		# Position 30 - Undefined
		$string .= '#';
		
		# Position 31 - Index
		$string .= '|';
		
		# Position 32 - Undefined
		$string .= '#';
		
		# Position 33-34 - Special format characteristics
		$string .= '||';	// No attempt to code
		
		# Position 35-37 - Language
		$string .= '###';	// No information provided
		
		# Position 38 - Modified record
		$string .= '#';		// Not modified
		
		# 39 - Cataloging source
		$string .= '|';		// No attempt to code
		
		# Register the result
		$this->fields['008'][0] = array (
			'_' => false,
			'' => $string,
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
	
	
	# 040 field - Cataloging Source; see: https://www.loc.gov/marc/bibliographic/bd040.html
	private function generate040 ()
	{
		# Register the result
		$this->fields['040'][0] = array (
			'a' => 'UkCU-P',
			'b' => 'eng',
			'c' => 'aacr',
		);
	}
	
	
	# Authors
	private function generateAuthors ($authors)
	{
		# End if none
		if (!$authors) {
			$this->fields['100'][] = array (
				'_' => '1#',
				'a' => 'Anonymous.',
			);
			return;
		}
		
		# Split by and
		$authors = explode (' and ', $authors);
		
		# Add each author
		foreach ($authors as $index => $author) {
			$author = trim ($author);
			$author = $this->formatInitials ($author);
			
			# Determine the field to use
			$field = ($index == 0 ? '110' : '710');
			
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
		# Register the result
		$this->fields['245'][0] = array (
			'_' => '10',
			'a' => 'Map of ' . $this->reformatWords ($title) . ($author ? ' /' : '.'),
		);
		
		# Author
		if ($author) {
			$this->fields['245'][0]['c'] = $this->dotEnd ($author);
		}
	}
	
	
	# Function to space out author initials correctly
	private function formatInitials ($author)
	{
		# Reformat if initials present
		if (preg_match ('/^(.+), ([A-Z])\.([A-Z])\.$/', $author, $matches)) {
			$author = "{$matches[1]}, {$matches[2]}. {$matches[3]}.";
		}
		
		# Return the string
		return $author;
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
			'a' => $this->dotEnd ('Scale ' . ($scale == 'no scale' ? 'not given' : $scale)),
		);
	}
	
	
	# Publication
	private function generatePublication ($publisher, $place, $date)
	{
		# Publisher
		$this->fields['260'][0] = array (
			'a' => $publisher,
		);
		
		# Place of publication
		if ($place) {
			$this->fields['260'][0]['b'] = $place;
		}
		
		# Date of publication (not of map depiction)
		if ($date) {
			$this->fields['260'][0]['c'] = $date;
		}
		
		# Add dot-end to last
		$lastKey = array_key_last ($this->fields['260'][0]);
		$this->fields['260'][0][$lastKey] = $this->dotEnd ($this->fields['260'][0][$lastKey]);
	}
	
	
	# Physical description
	private function generatePhysicalDescription ($number)
	{
		# Register the result
		$this->fields['300'][] = array (
			'a' => $this->dotEnd ((($number == 1 || !strlen ($number)) ? '1 map' : str_replace ('* ', ' ', $number . ' maps'))),
		);
	}
	
	
	# Note
	private function generateNotes ($notes)
	{
		# Register the result
		foreach ($notes as $note) {
			$note = trim ($note);
			if (substr_count (strtolower ($note), 'including duplicates')) {$note = 'Total includes duplicates.';}
			if (strlen ($note)) {
				$this->fields['500'][] = array (
					'a' => $this->dotEnd (ucfirst (strtolower ($note))),
				);
			}
		}
	}
	
	
	# Publication
	private function generateType ($type)
	{
		# End if none
		if (!strlen ($type)) {return false;}
		
		# Obtain the type; see authorised names at: https://id.loc.gov/authorities/subjects/sh85080858.html
		if (preg_match ('/(postcode|political|population)/', $type)) {
			$typeLoc = 'Maps';
		} else if (preg_match ('/(tourist)/', $type)) {
			$typeLoc = 'Tourist maps';
		} else {
			$typeLoc = 'Topographic maps';
		}
		
		# Register the result
		if ($typeLoc == 'Maps') {
			$this->fields['500'][] = array (
				'a' => ucfirst ($type) . ' maps',
			);
		}
		$this->fields['655'][] = array (
			'_' => '#0',
			'a' => $typeLoc,
		);
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
		$this->fields['852'][0]['j'] = "Drawer {$drawer} {$number}";
	}
	
	
	# Function to get the country code
	private function getCountryCode ($publisher)
	{
		# Define countries
		$countries = array (
			'xxk' => array (	// UK
				'Ordnance Survey',
				'Bartholomew',
				'Britannia',
				'Collins',
				'Geological Survey',
				'Scotland',
				'England',
				'GREAT BRITAIN',
				'War Office',
				'GSGS',
				'Ministry of Defence',
				'MoD',
				'Manchester',
				'Southampton',
				'Directorate of Overseas Surveys',
				'Directorate of Colonial Surveys',
				'42nd Survey Engineer Regiment',
				'London',
				'Royal Geographical Society',
				'BP Co. Ltd',
				'Ministry of Agriculture',
				'National Institute of Oceanography, GB',
				'Hydrographic Office',
			),
			'xxu' => array (	// US
				'American',
				'US Army',
				'National Geographic',
				'Department of Defence',
				'Geological Society of America',
				'US Department',
				'Kentucky',
				'USGS',
				'Washington',
				'US Naval',
				'Grand Canyon',
			),
			'bu#' => array (	// Bulgaria
				'Bulgaria',
			),
			'ru#' => array (	// Russia
				'Russia',
				'Moscow',
				'de Russie',
				'USSR',
				'Chicago',
			),
			'ug#' => array (	// Uganda
				'Uganda',
			),
			'ag#' => array (	// Argentina
				'Argentina',
			),
			'nz#' => array (	// New Zealand
				'New Zealand',
				'(NZ)',
				'NZ Lands and Survey',
			),
			'ii#' => array (	// India
				'India',
			),
			'cl#' => array (	// Chile
				'Instituto Geografico Militar',
			),
			'xr#' => array (	// Czech Republic
				'Czech Republic',
			),
			'fr#' => array (	// France
				'Paris',
				'Expeditions Polaires Francaises',
			),
			'xo#' => array (	// Slovakia
				'Slovak Republic',
				'Bratislava',
			),
			'dk#' => array (	// Danish
				'Geodaetisk Institut',
			),
			'fi#' => array (	// Finland
				'Finland',
			),
			'gw#' => array (	// Germany
				'Deutscher',
				'Hessischer',
				'German',
				'Stuttgart',
				'Deutsche',
			),
			'hu#' => array (	// Hungary
				'Cartographia',
			),
			'sz#' => array (	// Switzerland
				'Geodetic Inst',
			),
			'po#' => array (	// Portugal
				'Servicos Geologica',
				'Portugal',
			),
			'sa#' => array (	// South Africa
				'South Africa',
				'Pretoria',
				'Cape Town',
			),
			'ce#' => array (	// Sri Lanka
				'Sri-Lanka',
				'Ceylon',
			),
			'cl#' => array (	// Chile
				'Chile',
			),
			'mg#' => array (	// Madagascar
				'Madagascar',
			),
			'sj#' => array (	// Sudan
				'Khartoum',
			),
			'is#' => array (	// Israel
				'Israel',
			),
			'ne#' => array (	// Netherlands
				'Dutch',
			),
			'ke#' => array (	// Kenya
				'Kenya',
			),
			'bl#' => array (	// Brazil
				'Brasil',
			),
			'at#' => array (	// Australia
				'Australia',
				'Hobart',
				'Sydney',
				'Canberra',
				'Tasmania',
				'Brisbane',
			),
			'ja#' => array (	// Japan
				'Japan',
			),
			'sp#' => array (	// Spain
				'Madrid',
			),
			'rh#' => array (	// Zimbabwe
				'Rhodesia',
				'Zimbabwe',
			),
			'bl#' => array (	// Brazil
				'Rio de Janeiro',
			),
			'xxc' => array (	// Canada
				'Canada',
				'Ottawa',
			),
			'pl#' => array (	// Poland
				'Warsaw',
			),
			'xx#' => array (	// Unknown
				'Miscellaneous'
			),
		);
		
		# Loop through to do a string match
		$placeCode = 'xx#';
		if (strlen ($publisher)) {
			foreach ($countries as $code => $names) {
				foreach ($names as $name) {
					if (substr_count ($publisher, $name)) {
						$placeCode = $code;
						break 2;
					}
				}
			}
		}
		
		/*
		if ($placeCode == 'xx#') {
			echo $publisher . "<br />";
		}
		*/
		
		# Return the place code
		return $placeCode;
	}
}


# Polyfill function for array_key_last; see: https://www.php.net/manual/en/function.array-key-last.php#124007
if( !function_exists('array_key_last') ) {
    function array_key_last(array $array) {
        if( !empty($array) ) return key(array_slice($array, -1, 1, true));
    }
}


?>
