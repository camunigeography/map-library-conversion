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
			'div' => strtolower (__CLASS__),
			'useDatabase' => false,
			'tabUlClass' => 'tabsflat',
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
				'description' => 'Records, converted',
				'url' => '',
				'tab' => 'Home',
				'icon' => 'house',
			),
			'export' => array (
				'description' => 'Export MARC21 output',
				'tab' => 'Export',
				'url' => 'export/',
				'icon' => 'database_go',
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
		
		# Create MARC records from the data
		$marcText = $this->createMarcRecords ($data);
		
		# Display the records
		$html = $this->displayRecords ($marcText);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Export page
	public function export ()
	{
		# Load the data
		$data = $this->loadData ();
		
		# Create MARC records from the data
		$marcText = $this->createMarcRecords ($data);
		
		# Convert Bibcheck
		require_once ('createMarcExport.php');
		$createMarcExport = new createMarcExport ();
		$createMarcExport->createExport ($marcText, $errorsHtml);
		
		# Create links to the downloadable files
		$html  = "\n<h3>Download data</h3>";
		$html .= '
		<div class="graybox">
			<ul class="heavilyspaced">
				<li><a class="actions" href="' . $this->baseUrl . '/export/geog-maplibrary-marc.txt"><strong>MARC21 data (text)</strong></a></li>
				<li><a class="actions" href="' . $this->baseUrl . '/export/geog-maplibrary-marc.mrc"><strong>MARC21 data (binary .mrc)</strong></a></li>
			</ul>
		</div>
		';
		
		# Show Bibcheck errors
		$bibcheckErrorsFile = dirname (__FILE__) . '/export/geog-maplibrary-marc.errors.txt';
		$bibcheckErrors = trim (file_get_contents ($bibcheckErrorsFile));
		$html .= "\n<h3>Bibcheck errors</h3>";
		if ($bibcheckErrors) {
			$html .= "\n<pre>";
			$html .= htmlspecialchars ($bibcheckErrors);
			$html .= "\n</pre>";
		} else {
			$html .= "\n<p>{$this->tick} No Bibcheck errors.</p>";
		}
		
		# Show the HTML
		echo $html;
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
		# Load the MARC conversion class
		require_once ('marcConversion.php');
		$marcConversion = new marcConversion ();
		
		# Convert each record
		$marc = array ();
		foreach ($data as $record) {
			if ($marcRecord = $marcConversion->convertToMarc ($record)) {
				$marc[] = $marcRecord;
			}
		}
		
		# Compile to string
		$marc = implode ("\n\n", $marc);
		
		# Return the string
		return $marc;
	}
	
	
	# Function to display the records, with subfield highlighting
	public function displayRecords ($string)
	{
		# Highlight subfields
		$doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		$string = preg_replace ("/({$doubleDagger}[a-z0-9])/", '<strong>\1</strong>', $string);
		
		# Compile the HTML
		$html = "\n<pre>" . $string . "\n</pre>";
		
		# Return the HTML
		return $html;
	}
}

?>
