<?php

# Class to generate the MARC21 output as text
class createMarcExport
{
	# Constructor
	public function __construct ()
	{
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
	}
	
	
	
	# Main entry point
	public function createExport ($marcText, &$errorsHtml)
	{
		# Set the file directory
		$directory = dirname (__FILE__) . '/export';
		
		# Clear the current file(s)
		$filenameMarcTxt = $directory . "/geog-maplibrary-marc.txt";
		if (file_exists ($filenameMarcTxt)) {
			unlink ($filenameMarcTxt);
		}
		$filenameMrk = $directory . "/geog-maplibrary-marc.mrk";
		if (file_exists ($filenameMrk)) {
			unlink ($filenameMrk);
		}
		$filenameMrc = $directory . "/geog-maplibrary-marc.mrc";
		if (file_exists ($filenameMrc)) {
			unlink ($filenameMrc);
		}
		
		# Save the file, in the standard MARC format
		file_put_contents ($filenameMarcTxt, $marcText);
		
		# Create a binary version
		$this->marcBinaryConversion ($filenameMarcTxt, $filenameMrk, $filenameMrc);
		
		# Check the output
		$errorsFilename = $this->marcLintTest ($directory, $errorsHtml /* amended by reference */);
		
	}
	
	
	# Function to convert the MARC text to binary format, which creates a temporary .mrk intermediate version
	private function marcBinaryConversion ($marcTxtFile, $mrkFile, $mrcFile)
	{
		# Copy, so that a Voyager-specific formatted version can be created
		copy ($marcTxtFile, $mrkFile);
		
		# Reformat a MARC records file to Voyager input style
		$this->reformatMarcToVoyagerStyle ($mrkFile);
		
		# Clear output file if it currently exists
		if (file_exists ($mrcFile)) {
			unlink ($mrcFile);
		}
		
		# Define and execute the command for converting the text version to binary; see: http://marcedit.reeset.net/ and http://marcedit.reeset.net/cmarcedit-exe-using-the-command-line and http://blog.reeset.net/?s=cmarcedit
		$command = "mono /usr/local/bin/marcedit/cmarcedit.exe -s {$mrkFile} -d {$mrcFile} -pd -make";
		exec ($command, $output, $unixReturnValue);
		if ($unixReturnValue == 2) {
			echo "<p class=\"warning\">Execution of <tt>/usr/local/bin/marcedit/cmarcedit.exe</tt> failed with Permission denied - ensure the webserver user can read <tt>/usr/local/bin/marcedit/</tt>.</p>";
			return false;
		}
		/*
		foreach ($output as $line) {
			if (preg_match ('/^0 records have been processed/', $line)) {
				$mrcFilename = basename ($mrcFile);
				echo "<p class=\"warning\">Error in creation of MARC binary file ({$mrcFilename}): <tt>" . htmlspecialchars ($line) . '</tt></p>';
				break;
			}
		}
		*/
		
		# Delete the .mrk file
		unlink ($mrkFile);
	}
	
	
	# Function to reformat a MARC records file to Voyager input style
	public function reformatMarcToVoyagerStyle ($filenameMrk)
	{
		# Reformat to Voyager input style; this process is done using shelled-out inline sed/perl, rather than preg_replace, to avoid an out-of-memory crash
		exec ("sed -i 's/\\$/{dollar}/g' {$filenameMrk}");												// Protect $ with {dollar}; see: https://www.loc.gov/marc/makrbrkr.html and https://blog.reeset.net/archives/1905 , e.g. /records/115595/
		exec ("sed -i 's" . "/{$this->doubleDagger}\([a-z0-9]\)/" . '\$\1' . "/g' {$filenameMrk}");		// Replace double-dagger(s) with $
		exec ("sed -i '/^LDR /s/#/\\\\/g' {$filenameMrk}");												// Replace all instances of a # marker in the LDR field with \
		exec ("sed -i '/^008 /s/#/\\\\/g' {$filenameMrk}");												// Replace all instances of a # marker in the 008 field with \
		exec ("perl -pi -e 's" . '/^([0-9]{3}) #(.) (.+)$/' . '\1 \\\\\2 \3' . "/' {$filenameMrk}");	// Replace # marker in position 1 with \
		exec ("perl -pi -e 's" . '/^([0-9]{3}) (.)# (.+)$/' . '\1 \2\\\\ \3' . "/' {$filenameMrk}");	// Replace # marker in position 2 with \
		exec ("perl -pi -e 's" . '/^([0-9]{3}|LDR) (.+)$/' . '\1  \2' . "/' {$filenameMrk}");			// Add double-space after LDR and each field number
		exec ("perl -pi -e 's" . '/^([0-9]{3})  (.)(.) (.+)$/' . '\1  \2\3\4' . "/' {$filenameMrk}");	// Remove space after first and second indicators
		exec ("perl -pi -e 's" . '/^(.+)$/' . '=\1' . "/' {$filenameMrk}");								// Add = at start of each line
	}
	
	
	# Function to do a Bibcheck lint test
	private function marcLintTest ($directory, &$errorsHtml)
	{
		# Define the filename for the raw (unfiltered) errors file and the main filtered version
		$errorsFilename = "{$directory}/geog-maplibrary-marc.errors.txt";
		$errorsUnfilteredFilename = str_replace ('errors.txt', 'errors-unfiltered.txt', $errorsFilename);
		
		# Clear file(s) if currently existing
		if (file_exists ($errorsFilename)) {
			unlink ($errorsFilename);
		}
		if (file_exists ($errorsUnfilteredFilename)) {
			unlink ($errorsUnfilteredFilename);
		}
		
		# Define Bibcheck location
		$applicationRoot = dirname (__FILE__);
		
		# Define and execute the command for converting the text version to binary, generating the errors listing file; NB errors.txt is a hard-coded location in Bibcheck, hence the file-moving requirement
		# If an error occurs, e.g. two LDRs, Bibcheck will output the errors file until the point the errors occurred, e.g. "Invalid indicators "00273nas\a22000977\\4500" forced to blanks in record 2523 for tag LDR \n no subfield data found in record 2523 for tag LDR"
		$command = "cd {$applicationRoot}/libraries/bibcheck/ ; PERL5LIB={$applicationRoot}/libraries/bibcheck/ perl lint_test.pl {$directory}/geog-maplibrary-marc.mrc 2>&1";	// 2>> errors.txt
		$output = shell_exec ($command);
		if ($output) {
			$errorsHtml .= "\n<p class=\"warning\">Error in Bibcheck execution for fileset: " . nl2br (str_replace ("\n\n", "\n", str_replace ("\n\r", "\n", htmlspecialchars (trim ($output))))) . '</p>';
		}
		$command = "cd {$applicationRoot}/libraries/bibcheck/ ; mv errors.txt {$errorsUnfilteredFilename}";
		$output = shell_exec ($command);
		
		# Strip whitelisted errors and save a filtered version
		$errorsString = file_get_contents ($errorsUnfilteredFilename);
		$errorsString = $this->stripBibcheckWhitelistErrors ($errorsString);
		file_put_contents ($errorsFilename, $errorsString);
		
		# Return the filename
		return $errorsFilename;
	}
	
	
	# Function to strip whitelisted errors from the Bibcheck reports
	private function stripBibcheckWhitelistErrors ($errorsString)
	{
		# Define errors to whitelist
		$whitelistErrorRegexps = array (
			'008: Check place code xxk - please set code for specific UK member country eg England, Wales \(if known\).',
		);
		
		# Split the file into individual reports
		$delimiter = str_repeat ('=', 63);	// i.e. the ===== line
		$reportsUnfiltered = explode ($delimiter, $errorsString);
		
		# Filter out lines for each report
		$reports = array ();
		foreach ($reportsUnfiltered as $index => $report) {
			
			# Strip out lines matching a whitelisted error type
			$lines = explode ("\n", $report);
			foreach ($lines as $lineIndex => $line) {
				foreach ($whitelistErrorRegexps as $whitelistErrorRegexp) {
					if (preg_match ('/' . addcslashes ($whitelistErrorRegexp, '/') . '/', $line)) {
						unset ($lines[$lineIndex]);
						break;	// Break out of regexps loop and move to next line
					}
				}
			}
			$report = implode ("\n", $lines);
			
			# If there are no errors remaining in this report, skip re-registering the report
			if (preg_match ('/\^{25}$/D', trim ($report))) {		// i.e. no errors if purely whitespace between ^^^^^ line and the end
				continue;	// Skip to next report
			}
			
			# Re-register the report
			$reports[$index] = $report;
		}
		
		# Reconstruct as a single listing
		$errorsString = implode ($delimiter, $reports);
		
		# Return the new listing
		return $errorsString;
	}
}

?>
