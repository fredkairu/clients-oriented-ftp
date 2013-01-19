<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * files that are being uploaded.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

class PSend_Upload_File
{

	var $folder;
	var $assign_to;
	var $uploader;
	var $file;
	var $name;
	var $description;
	var $upload_state;
	/**
	 * the $separator is used to replace invalid characters on a file name.
	 */
	var $separator = '_';
	
	/**
	 * Check if the file extension is among the allowed ones, that are defined on
	 * the options page.
	 */
	function is_filetype_allowed($filename)
	{
		global $options_values;
		$this->safe_filename = $filename;
		$allowed_file_types = str_replace(',','|',$options_values['allowed_file_types']);
		$file_types = "/^\.(".$allowed_file_types."){1}$/i";
		if (preg_match($file_types, strrchr($this->safe_filename, '.'))) {
			return true;
		}
	}
	
	/**
	 * Generate a safe filename that includes only letters, numbers and underscores.
	 * If there are multiple invalid characters in a row, only one replacement character
	 * will be used, to avoid unnecessarily long file names.
	 */
	function safe_rename($name)
	{
		$this->name = $name;
		$this->safe_filename = preg_replace('/[^\w\._]+/', $this->separator, $this->name);
		return $this->safe_filename;
	}
	
	/**
	 * Rename a file using only letters, numbers and underscores.
	 * Used when reading the temp folder to add files to ProjectSend via the "Add from FTP"
	 * feature.
	 *
	 * Files are renamed before being shown on the list.
	 *
	 */
	function safe_rename_on_disc($name,$folder)
	{
		$this->name = $name;
		$this->folder = $folder;
		$this->new_filename = preg_replace('/[^\w\._]+/', $this->separator, $this->name);
		if(rename($this->folder.'/'.$this->name, $this->folder.'/'.$this->new_filename)) {
			return $this->new_filename;
		}
		else {
			return false;
		}
	}
	
	/**
	 * Used to copy a file from the temporary folder (the default location where it's put
	 * after uploading it) to the assigned client's personal folder.
	 * If succesful, the original file is then deleted.
	 */
	function upload_move($arguments)
	{
		$this->uploaded_name = $arguments['uploaded_name'];
		$this->filename = $arguments['filename'];

		$this->file_final_name = time().'-'.$this->filename;
		$this->path = UPLOADED_FILES_FOLDER.'/'.$this->file_final_name;
		if (rename($this->uploaded_name, $this->path)) {
			chmod($this->path, 0644);
			return $this->path;
		}
		else {
			return false;
		}
	}
	
	/**
	 * Called after correctly moving the file to the final location.
	 */
	function upload_add_to_database($arguments)
	{
		global $database;
		$this->post_file = $arguments['file'];
		$this->name = encode_html($arguments['name']);
		$this->description = encode_html($arguments['description']);
		$this->assign_to = $arguments['assign_to'];
		$this->uploader = $arguments['uploader'];
		$this->hidden = (!empty($arguments['hidden'])) ? '1' : '0';
		$this->timestamp = time();

		$result = $database->query("INSERT INTO tbl_files (url, filename, description, timestamp, uploader)"
									."VALUES ('$this->post_file', '$this->name', '$this->description', '$this->timestamp', '$this->uploader')");

		$this->file_id = mysql_insert_id();

		if (!empty($this->assign_to)) {
			foreach ($this->assign_to as $this->assignment) {
				switch ($this->assignment[0]) {
					case 'c':
						$add_to = 'client_id';
						break;
					case 'g':
						$add_to = 'group_id';
						break;
				}
				$this->assignment = substr($this->assignment, 1);
				$assign_file = $database->query("INSERT INTO tbl_files_relations (file_id, $add_to, hidden, timestamp)"
											."VALUES ('$this->file_id', '$this->assignment', '$this->hidden', '$this->timestamp')");
			}
		}

		if(!empty($result)) {
			return true;
		}
		else {
			return false;
		}
	}

}

?>