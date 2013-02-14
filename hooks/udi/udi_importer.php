<?php
/**
 * Classes and functions for importing data to LDAP
 *
 * These classes provide differnet import formats.
 *
 * @author Piers Harding
 * @package phpLDAPadmin
 */

/**
 * Importer Class
 *
 * This class serves as a top level importer class, which will return
 * the correct Import class.
 * This is derived from the core PLA LDIF import functionality, and has been
 * specialised for CSV files
 *
 * @package phpLDAPadmin
 * @subpackage UDIImport
 */
class Importer {
    # Server ID that the export is linked to
    private $server_id;
    # Import Type
    private $template_id;
    private $template;
    private $delimiter;

    /**
     * Constructor - this builds the environment connected to the
     * currently selected LDAP server, and hands off to CSV file
     * importer object
     *
     * @param Integer $server_id LDAP server connection Id
     * @param Integer $template_id Id of template
     * @param char $delimiter delimiter of the file
     */
    public function __construct($server_id, $template_id, $delimiter) {
        $this->server_id = $server_id;
        $this->template_id = $template_id;
        $this->delimiter = $delimiter;

        $this->accept();
    }

    /**
     * Accept the file for upload from the browser
     * then trigger the initial file processing
     *
     */
    private function accept() {
        switch($this->template_id) {
            case 'CSV':
                if (isset($_FILES['csv_file']) && is_array($_FILES['csv_file']) && ! $_FILES['csv_file']['error']) {
                    $this->template = new ImportCSV($this->server_id, $_FILES['csv_file']['tmp_name'], $_FILES['csv_file']['name']);
                } else {
                    system_message(array(
                        'title'=>_('No UDI import input'),
                        'body'=>_('You must specify a file for upload.'),
                        'type'=>'error'),
                    sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
                    get_request('udi_nav','REQUEST'),
                    get_request('server_id','REQUEST')));
                    die();
                }
                break;

            default:
                system_message(array(
                    'title'=>sprintf('%s %s',_('Unknown Import Type'),$this->template_id),
                    'body'=>_('phpLDAPadmin has not been configured for that import type'),
                    'type'=>'warn'),'index.php');
                die();
        }
        $this->template->accept($this->delimiter);
    }

    /**
     * accessor for the template object
     *
     * @return object template
     */
    public function getTemplate() {
        return $this->template;
    }
}

/**
 * Import Class
 *
 * This abstract classes provides all the common methods and variables for the
 * custom import classes.
 * Copied from the original abstract class for PLA LDIF file imports -
 * specialised for CSV files, including selection of file delimiter.
 *
 * @package phpLDAPadmin
 * @subpackage UDIImport
 */
abstract class Import {
    protected $server_id = null;
    protected $filename;
    protected $realname;
    protected $input = null;
    protected $source = array();
    protected $delimiter;
    protected $stamp;
    public $version = null;
    public $school = null;

    /**
     * Constructor
     * hook in the current directory environment, and the file details
     *
     * @param Integer $server_id - LDAP connection Id
     * @param String $file file - could be tmp name
     * @param String $realname name of the file
     */
    public function __construct($server_id, $file, $realname='') {
        $this->server_id = $server_id;
        $this->filename = $file;
        $this->realname = ($realname ? $realname : $file);
    }

    /**
     * Accept the file - pull in it's contents and split into lines
     *
     * @param Char $delimiter delimiter of csv file - tab, ',', ;
     */
    public function accept($delimiter) {
        $this->delimiter = $delimiter;
        $external = (preg_match('/^https?:\/\//', $this->filename) ? true : false);
        if (file_exists($this->filename) || $external) {
            $this->source['name'] = $this->realname;
            if (!$external) {
                $this->source['size'] = filesize($this->filename);
            }
            // can we read this file?
            if (!$external && (!is_file($this->filename) || !is_Readable($this->filename))) {
                // die a death
                system_message(array(
                    'title'=>_('Cannot read file'),
                    'body'=>_('You must specify a valid file for upload: ').$this->realname,
                    'type'=>'error'),
                sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
                get_request('udi_nav','REQUEST'),
                get_request('server_id','REQUEST')));
                die();
            }
            $input = file_get_contents($this->filename);
            if (empty($input)) {
                system_message(array(
                    'title'=>_('Input is empty'),
                    'body'=>_('You must specify a valid file for upload: ').$this->realname,
                    'type'=>'error'),
                sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
                get_request('udi_nav','REQUEST'),
                get_request('server_id','REQUEST')));
                die();
            }
            if ($external) {
                $this->source['size'] = strlen($input);
            }
            $this->input = preg_split("/\n|\r\n|\r/",$input);
            // 2010-09-28 11:20:40
            if (!empty($this->input) && is_array($this->input)) {
                // check the last populated row for a timestamp
                end($this->input);
                $last = current($this->input);
                while (!empty($this->input) && empty($last)) {
                    array_pop($this->input);
                    end($this->input);
                    $last = current($this->input);
                }
                reset($this->input);
                // 0119#1.71#2013-02-14 07:02:34
                if (preg_match('/^(\d{4})\#1\.71\#(\d\d\d\d\-\d\d\-\d\d \d\d\:\d\d\:\d\d)/', $last, $matches)) {
                    $this->version = '1.71';
                    $this->school = $matches[1];
                    $this->stamp = $matches[2];
                    // discard timestamp record
                    array_pop($this->input);
                }
                // 2013-02-14 07:02:34 // old 1.6 version
                else if (preg_match('/\d\d\d\d\-\d\d\-\d\d \d\d\:\d\d\:\d\d/', $last)) {
                    $this->version = '1.6';
                    $this->stamp = $last;
                    // discard timestamp record
                    array_pop($this->input);
                }
            }

        } else {
            system_message(array(
                'title'=>_('No UDI import input'),
                'body'=>_('You must specify a valid file for upload: ').$this->realname,
                'type'=>'error'),
            sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
            get_request('udi_nav','REQUEST'),
            get_request('server_id','REQUEST')));
            die();
        }
    }

    /**
     * Accessor for source file details
     *
     * @param $attr - which source file detail - name/size
     *
     * @return String file attrbute value
     */
    public function getSource($attr) {
        if (isset($this->source[$attr])) {
            return $this->source[$attr];
        }
        return null;
    }
}

/**
 * Import entries from the UDI CSV file format
 * Implementation of abstract class
 *
 *
 * @package phpLDAPadmin
 * @subpackage UDIImport
 */
class ImportCSV extends Import {
    private $_currentLineNumber = 0;
    private $_currentLine = '';
    private $_valid_attrs;
    private $_headers;
    private $_header_line;
    private $template;
    public $error = array();

    /**
     * Accept the file details, and then grab the first line
     * as a header
     *
     * Validate the header line - this gives assurance that:
     * a) it is actually a headerline
     * b) that it will map to directory elements
     *
     * @param String $delimiter csv file delimiter
     */
    public function accept($delimiter) {
        global $app;
        parent::accept($delimiter);
        $this->_header_line = $this->readEntry(true);
        $this->_header_line = $this->_header_line['data'];

        // header must exists
        if (empty($this->_header_line)) {
            system_message(array(
                'title'=>_('No valid header line in UDI import'),
                'body'=>_('You must specify a valid file for upload, with the first record being the column headings.'),
                'type'=>'error'),
            sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
            get_request('udi_nav','REQUEST'),
            get_request('server_id','REQUEST')));
            die();
        }

        // mandatory headers must exist
        $socs = $app['server']->SchemaObjectClasses('user');
        $mlepPerson = $socs['mlepperson'];
        $must = $mlepPerson->getMustAttrs();
        $dmo_attrs = $app['server']->SchemaAttributes('user');
        $dmo_attrs = array_merge(array("mlepgroupmembership" => new ObjectClass_ObjectClassAttribute("mlepgroupmembership", "mlepGroupMembership")), $dmo_attrs);
        //        $this->_valid_attrs = $dmo_attrs;
        $headers = array();
        foreach ($this->_header_line as $hdr) {
            $headers[strtolower($hdr)]= $hdr;
        }
        foreach ($must as $attr) {
            if (!isset($headers[$attr->getName()])) {
                system_message(array(
                    'title'=>_('UDI import mandatory field missing: ').$attr->getName(false),
                    'body'=>_('You must specify a valid file for upload, with the first record being the column headings, that must atleast contain all the mandatory fields, and fields that correspond to valid LDAP schema attributes.'),
                    'type'=>'error'),
                sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
                get_request('udi_nav','REQUEST'),
                get_request('server_id','REQUEST')));
                die();
            }
        }

        // No longer enforcing this check as it should enable superfluous fields in the file to be ignored
        // all other headers must exist in the schema as a valid attribute
//        foreach ($headers as $hdr => $name) {
//            if (!isset($dmo_attrs[$hdr])) {
//                system_message(array(
//                    'title'=>_('UDI import illegal field specified: ').$name,
//                    'body'=>_('You must specify a valid file for upload, with the first record being the column headings, that must atleast contain all the mandatory fields, and fields that correspond to valid LDAP schema attributes.'),
//                    'type'=>'error'),
//                sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
//                get_request('udi_nav','REQUEST'),
//                get_request('server_id','REQUEST')));
//                die();
//            }
//        }

        // save headers
        $this->_headers = $headers;

    }

    /**
     * Accessor for the unpacked csv header line
     *
     * @return array header line columns
     */
    public function getCSVHeader() {
        return $this->_header_line;
    }

    /**
     * declaration of this type of object
     *
     * @return array of class attributes
     */
    static public function getType() {
        return array('type'=>'CSV','description' => _('CSV Import'),'extension'=>'csv');
    }

    /**
     * Accessor for template object
     *
     * @return object the template
     */
    protected function getTemplate() {
        return $this->template;
    }

    /**
     * Get the current LDAP server object
     *
     * @return object server
     */
    protected function getServer() {
        return $_SESSION[APPCONFIG]->getServer($this->server_id);
    }

    /**
     * Get the next line of the file
     *
     * @param bool $header true - get the header line
     *
     * @return the next line of the file or false
     */
    public function readEntry($header=false) {

        if ($line = $this->nextLine($header)) {
            return array('data' => $line, 'lineno' => $this->_currentLineNumber);
        }
        else {
            return false;
        }
    }

    /**
     * Get the line of the next entry
     *
     * @return The lines (unfolded) of the next entry
     */
    private function nextLine($header=false) {

        if (!$this->eof()) {
            $this->advanceNextLine();
            while (!$this->eof() && ($this->isCommentLine() || $this->isBlankLine())) {
                $this->advanceNextLine();
            }
            if ($this->isCommentLine() || $this->isBlankLine()) {
                return false;
            }
            else {
                if ($header) {
                    return str_getcsv(trim($this->_currentLine), $this->delimiter);
                }
                return $this->validateLine();
            }
        }
        else {
            return false;
        }
    }

    /**
     * Get the line of the next entry - check that it has the correct number of
     * columns
     *
     * @return The lines (unfolded) of the next entry
     */
    private function validateLine() {
        $line = str_getcsv(trim($this->_currentLine), $this->delimiter);

        if (count($line) != count($this->_headers)) {
            system_message(array(
                'title'=>sprintf(_('UDI import invalid record: %s'), $this->_currentLineNumber),
                'body'=>sprintf(_('You must specify a valid file for upload, with all records having the same number and type of column as specified by the column headings. <br/> Record no. %s is: %s <br/> Header is: %s'), $this->_currentLineNumber, $this->_currentLine, implode(', ', $this->_header_line)),
                'type'=>'error'),
            sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
            get_request('udi_nav','REQUEST'),
            get_request('server_id','REQUEST')));
            die();
        }

        return $line;
    }

    /**
     * Get the line of the next entry
     *
     * @return The lines (unfolded) of the next entry
     */
    private function advanceNextLine() {

        $this->_currentLineNumber++;
        $this->_currentLine = array_shift($this->input);
    }

    /**
     * Check if it's a comment line.
     *
     * @return boolean true if it's a comment line,false otherwise
     */
    private function isCommentLine() {
        return substr(trim($this->_currentLine),0,1) == '#' ? true : false;
    }

    /**
     * Check if is the current line is a blank line.
     *
     * @return boolean if it is a blank line,false otherwise.
     */
    private function isBlankLine() {
        $blank = trim($this->_currentLine);
        return empty($blank) ? true : false;
    }

    /**
     * Returns true if we reached the end of the input.
     *
     * @return boolean true if it's the end of file, false otherwise.
     */
    public function eof() {
        return count($this->input) > 0 ? false : true;
    }

    /**
     * Store errors for later display
     *
     * @param String $msg message to display
     * @param String $data to attach to error
     */
    private function error($msg,$data) {
        $this->error['message'] = sprintf('%s [%s]',$msg,$this->template ? $this->template->getDN() : '');
        $this->error['line'] = $this->_currentLineNumber;
        $this->error['data'] = $data;
        $this->error['changetype'] = $this->template ? $this->template->getType() : 'Not set';

        return false;
    }
}

?>