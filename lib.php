<?php

// This file is part of the Certificate module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Handles uploading files
 *
 * @package    local_expreport
 * @copyright  Manjunath B K<manjunathbk@elearn10.com>
 * @copyright  Edzlearn Services Pvt Limited <lmsofindia.com>
 * @license    http://www.lmsofindia.com 2017 or later
 */
require_once("{$CFG->libdir}/completionlib.php");
require_once("$CFG->libdir/gradelib.php");

/**
* Manjunath: Function to get all the users who are matched the filter *criteria.
* @param array $data contains all form data.
* @return array $finaluserarray contains all the user's id matched *the *criteria.
**/
function expreport_users_matched_filter_criteria($data){
	global $DB,$CFG;
	//Getting the course id's from settings.
	$formdataarray = (array)$data;
	$config = get_config('expreport');
	$allcourses = $config->courseids;
	$userfields = $config->profilefields;
	$exploadfields = explode(",", $userfields);
	$allusers = [];
	//getting all the enrolled users in these courses.s
	if(!empty($allcourses)){
		$allcourseids = explode(",", $allcourses);
		foreach ($allcourseids as $courseid) {
			//get the enrolled users in this course.
			$enrolledusers = expreport_get_enroled_userdata_expreport($courseid);
			$allusers = array_unique(array_merge($allusers,$enrolledusers));
		}
		sort($allusers);
	}
	//check the enrolled users satisfy all the conditions.
	$finaluserarray = [];
	foreach ($allusers as $user) {
		$status = 0;
		foreach ($exploadfields as $userfield) {
			$fielddata = $DB->get_record('user_info_field',array('shortname'=>$userfield));
			if(!empty($fielddata)){
				if(!empty($fielddata) && $fielddata->datatype =='text' || $fielddata->datatype =='menu' || $fielddata->datatype =='checkbox' || $fielddata->datatype =='datetime'){

					$result = $DB->get_records_sql("SELECT * FROM {user_info_data} WHERE fieldid ='$fielddata->id' AND data='$formdataarray[$userfield]' AND userid='$user' AND data != ' '");

					if(!empty($result)){
						$status = 1;
					}

				}else if(!empty($fielddata) && $fielddata->datatype =='textarea'){
					$textdata = $formdataarray[$userfield]['text'];
					$result = $DB->get_records_sql("SELECT * FROM {user_info_data} WHERE fieldid ='$fielddata->id' AND data='$textdata' AND userid='$user' AND data != ' '");
					if(!empty($result)){
						$status = 1;
					}
				}

			}
		}
		if($status){
			$finaluserarray[] = $user;
		}
		$status = 0;
	}
	return $finaluserarray;
}


/**
* Manjunath: Function will give list of enrollement users inside courses.
* @param string $courseid courseid.
* @return array returning $listofusers.
**/
function expreport_get_enroled_userdata_expreport($courseid){
	global $DB,$CFG;
	$allenrolleduser= enrol_get_course_users($courseid);
	foreach ($allenrolleduser as $user) {
		$listofusers[] = $user->id;
	}
	if(!empty($listofusers)){
		sort($listofusers);
		return $listofusers;
	}
}

/**
*Manjunath: Function will give the complete html of the tabe which *will be displaying once user submit the form.
*@param obect $allusers will have all the users list who satisfy the *report conditions.
*@return string $reporttable will contain all the html needed for *table creation in the main page.
*/
function expreport_report_table($allusers){
	global $DB,$CFG;
	$config = get_config('expreport');
	$allcourses = $config->courseids;
	$allcourseids = explode(",", $allcourses);
	$userfields = $config->profilefields;
	$exploadvalus = explode(",", $userfields);

	$reporttable="";
	$reporttable.=html_writer::start_tag('table',array('id'=>'myTable'));
	//including table header here.
	$reporttable.=expreport_table_header_expreport();
	//including table header ends here.
	//including table data here.
	$reporttable.=html_writer::start_tag('tbody');
	foreach ($allcourseids as $courseid) {
		//creating course object from course id.
		$course = $DB->get_record('course',array('id'=>$courseid));
		//creating rows for all enrolled users in this course.
		foreach ($allusers as $userid) {
			$user = $DB->get_record('user',array('id'=>$userid));
			$activities = expreport_all_activity_details($course,$user);
			if(!empty($activities)){
				foreach ($activities as $activity) {
					$reporttable.=html_writer::start_tag('tr');
					$reporttable.=html_writer::start_tag('td');
					$reporttable.=$user->username;
					$reporttable.=html_writer::end_tag('td');
					$reporttable.=html_writer::start_tag('td');
					$reporttable.=fullname($user);
					$reporttable.=html_writer::end_tag('td');
					//adding user custom fields.
					foreach ($exploadvalus as $fvalue) {
						$fieldinfo = $DB->get_record('user_info_field',array('shortname'=>$fvalue));
						if(!empty($fieldinfo)){
							$fielddata = $DB->get_record('user_info_data',array('userid'=>$userid,'fieldid'=>$fieldinfo->id));
							if(!empty($fielddata)){
								$reporttable.=html_writer::start_tag('td');
								$reporttable.=$fielddata->data;
								$reporttable.=html_writer::end_tag('td');
							}else{
								$reporttable.=html_writer::start_tag('td');
								$reporttable.="-";
								$reporttable.=html_writer::end_tag('td');
							}
						}
					}
					//adding user custom fields ends.
					$reporttable.=html_writer::start_tag('td');
					$reporttable.=$course->fullname;
					$reporttable.=html_writer::end_tag('td');

					$cinfo = new completion_info($course);
					$iscomplete = $cinfo->is_course_complete($userid);
					if(!empty($iscomplete)){
						$status=get_string('complet','local_expreport');
					}else{
						$status=get_string('notcomplete','local_expreport');
					}
					$reporttable.=html_writer::start_tag('td');
					$reporttable.=$status;
					$reporttable.=html_writer::end_tag('td');
					//
					$reporttable.=html_writer::start_tag('td');
					$reporttable.=$activity['activityname'];
					$reporttable.=html_writer::end_tag('td');
					$reporttable.=html_writer::start_tag('td');
					$reporttable.=$activity['activitystatus'];
					$reporttable.=html_writer::end_tag('td');
					$reporttable.=html_writer::start_tag('td');
					$reporttable.=$activity['activitygrade'];
					$reporttable.=html_writer::end_tag('td');
					$reporttable.=html_writer::end_tag('tr');
				}
			}
		}
	}
	$reporttable.=html_writer::end_tag('tbody');
	//including table data ends here.
	$reporttable.=html_writer::end_tag('table');
	return $reporttable;
}

/**
*Manjunath: This function will give all activity status of user in *particular course. Only those activities are considered which are *considered for course completion.
*@param object $course contains complete course info.
*@param object $user contains complete user info.
*@return array $dataarray contains all the activity status of user in *this course. Having activity name, activity status and activity *grade.
*/
function expreport_all_activity_details($course,$user){
	global $DB;
	$dataarray=[];
// Get course completion data.
	$info = new \completion_info($course);
// Load criteria to display.
	$completions = $info->get_completions($user->id);
// Check this user is enroled.
	if ($info->is_tracked_user($user->id)) {
// Loop through course criteria.
		$notcomplete='';
		foreach ($completions as $completion) {
			$criteria = $completion->get_criteria();
			$complete = $completion->is_complete();
			if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_ACTIVITY) {
				$activities[$criteria->moduleinstance] = $complete;
				$modinfo = get_fast_modinfo($course);
				$cm = $modinfo->get_cm($criteria->moduleinstance);
				$activityname=$cm->name;
				$completion = new \completion_info($course);
				$activitycompletiondata = $completion->get_data($cm, true, $user->id);
				if($activitycompletiondata->completionstate == 1){
					$status = get_string('complet','local_expreport');
				}else if($activitycompletiondata->completionstate == 0){
					$status = get_string('notcomplete','local_expreport');
				}
				//getting grade of the particular activity.
				$activitygrade =expreport_get_activity_grade_expreport($course,$cm,$user);
				$dataarray[]=array("activityname"=>$activityname,
					"activitystatus"=>$status,
					"activitygrade"=>$activitygrade);
			}
		}
	}
	return $dataarray;
}

/**
*Manjunath: This function will give the dynamic header for the report *table based on the conditions set in the settings page.
*@return string $reporttable contains complete header html needed for *the report generation.
*/
function expreport_table_header_expreport(){
	global $DB;
	$config = get_config('expreport');
	$allcourses = $config->courseids;
	$allcourseids = explode(",", $allcourses);
	$userfields = $config->profilefields;
	$exploadvalus = explode(",", $userfields);
	$reporttable="";
	$reporttable.=html_writer::start_tag('thead');
	$reporttable.=html_writer::start_tag('tr');
	$reporttable.=html_writer::start_tag('th');
	$reporttable.=get_string('username','local_expreport');
	$reporttable.=html_writer::end_tag('th');
	$reporttable.=html_writer::start_tag('th');
	$reporttable.=get_string('fullname','local_expreport');
	$reporttable.=html_writer::end_tag('th');
	foreach ($exploadvalus as $value) {
		$fieldvalues = $DB->get_record('user_info_field',array('shortname'=>$value));
		if(!empty($fieldvalues)){
			$reporttable.=html_writer::start_tag('th');
			$reporttable.=$fieldvalues->name;
			$reporttable.=html_writer::end_tag('th');
		}
	}
	$reporttable.=html_writer::start_tag('th');
	$reporttable.=get_string('coursename','local_expreport');
	$reporttable.=html_writer::end_tag('th');
	$reporttable.=html_writer::start_tag('th');
	$reporttable.=get_string('coursecompletion','local_expreport');
	$reporttable.=html_writer::end_tag('th');
	$reporttable.=html_writer::start_tag('th');
	$reporttable.=get_string('activityname','local_expreport');
	$reporttable.=html_writer::end_tag('th');
	$reporttable.=html_writer::start_tag('th');
	$reporttable.=get_string('activitystatus','local_expreport');
	$reporttable.=html_writer::end_tag('th');
	$reporttable.=html_writer::start_tag('th');
	$reporttable.=get_string('activitygrade','local_expreport');
	$reporttable.=html_writer::end_tag('th');
	$reporttable.=html_writer::end_tag('tr');
	$reporttable.=html_writer::end_tag('thead');
	return $reporttable;
}
/**
*Manjunath: This function will give activity graded for a particular *activity for an user in course.
*@param object $course complete course info.
*@param object $cm contains complete activity info.
*@param object $user contains complete user info.
*@return int $activitygrade will return the activity grade for the *user in this activity.
*/
function expreport_get_activity_grade_expreport($course,$cm,$user){
	global $DB;
	$activitygrade="-";
	$grades = grade_get_grades($course->id, 'mod', $cm->modname, $cm->instance,$user->id);
	if(!empty($grades->items)){
		foreach ($grades->items as $gradekey => $gradevalue) {
			if($gradevalue->locked != 1){
				$activityname = $gradevalue->name;
				foreach ($gradevalue->grades as $gkey => $gvalue) {
					$activitygrade = $gvalue->grade;
				}
			}
		}
	}
	return $activitygrade;
}

/**
*Manjunath: this function will have complete header data needed for *the report generation based on the conditions set in the setting.
*@return array $header contains all the header data.
*/
function expreport_header_data(){
	global $DB;
	$config = get_config('expreport');
	$allcourses = $config->courseids;
	$allcourseids = explode(",", $allcourses);
	$userfields = $config->profilefields;
	$exploadvalus = explode(",", $userfields);
	$header=[];
	$header[]=get_string('username','local_expreport');
	$header[]=get_string('fullname','local_expreport');
	foreach ($exploadvalus as $value) {
		$fieldvalues = $DB->get_record('user_info_field',array('shortname'=>$value));
		if(!empty($fieldvalues)){
			$header[]=$fieldvalues->name;
		}
	}
	$header[]=get_string('coursename','local_expreport');
	$header[]=get_string('coursecompletion','local_expreport');
	$header[]=get_string('activityname','local_expreport');
	$header[]=get_string('activitystatus','local_expreport');
	$header[]=get_string('activitygrade','local_expreport');
	return $header;
}

/**
*Manjunath: This function will return all the report data needed for *the report generation.
*@param object $allusers contains all the users who matched the *report conditions.
*@return array $tabledata contains complete table data for report *generation.
*/
function expreport_table_data($allusers){
	global $DB;
	$config = get_config('expreport');
	$allcourses = $config->courseids;
	$allcourseids = explode(",", $allcourses);
	$userfields = $config->profilefields;
	$exploadvalus = explode(",", $userfields);
	$header = expreport_header_data();
	$tabledata=[];
	$tabledata[]=$header;
	foreach ($allcourseids as $courseid) {
		//creating course object from course id.
		$course = $DB->get_record('course',array('id'=>$courseid));
		//creating rows for all enrolled users in this course.
		foreach ($allusers as $userid) {
			$user = $DB->get_record('user',array('id'=>$userid));
			$activities = expreport_all_activity_details($course,$user);
			if(!empty($activities)){
				foreach ($activities as $activity) {
					$temp=[];
					$temp[]=$user->username;
					$temp[]=fullname($user);
					//adding user custom fields.
					foreach ($exploadvalus as $fvalue) {
						$fieldinfo = $DB->get_record('user_info_field',array('shortname'=>$fvalue));
						if(!empty($fieldinfo)){
							$fielddata = $DB->get_record('user_info_data',array('userid'=>$userid,'fieldid'=>$fieldinfo->id));
							if(!empty($fielddata)){
								$temp[] = $fielddata->data;
							}else{
								$temp[] = "-";
							}
						}
					}
					//adding user customfield data ends.
					$temp[]=$course->fullname;
					$cinfo = new completion_info($course);
					$iscomplete = $cinfo->is_course_complete($userid);
					if(!empty($iscomplete)){
						$status=get_string('complet','local_expreport');
					}else{
						$status=get_string('notcomplete','local_expreport');
					}
					$temp[]=$status;
					$temp[]=$activity['activityname'];
					$temp[]=$activity['activitystatus'];
					$temp[]=$activity['activitygrade'];
					$tabledata[]=$temp;
				}
			}
		}
	}
	return $tabledata;
}

/**
*Manjunath: This function is needed to retrive the files added to *moodledata.
*/
function local_expreport_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
// Make sure the filearea is one of those used by the plugin.
    // if ($filearea !== 'rhbgimage') {
    //  return false;
    // }

// Make sure the user is logged in and has access to the module (plugins that are not course modules should leave out the 'cm' part).
	require_login();

// Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
$itemid = array_shift($args); // The first item in the $args array.

// Use the itemid to retrieve any relevant data records and perform any security checks to see if the
// user really does have access to the file in question.

// Extract the filename / filepath from the $args array.
$filename = array_pop($args); // The last item in the $args array.
if (!$args) {
$filepath = '/'; // $args is empty => the path is '/'
} else {
$filepath = '/'.implode('/', $args).'/'; // $args contains elements of the filepath
}

// Retrieve the file from the Files API.
$fs = get_file_storage();
$file = $fs->get_file($context->id, 'local_expreport', $filearea, $itemid, $filepath, $filename);
if (!$file) {
return false; // The file does not exist.
}
// We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
// From Moodle 2.3, use send_stored_file instead.
//send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!

$forcedownload = true;
send_file($file, $file->get_filename(), true, $forcedownload, $options);
}

/**
*Manjunath: This function is called during schedule task. This *function will check which all reports are not sent yet and will send *those reports. 
*After sending the mails will update the mailsent flag to 1 in *local_expreport table.
*/
function expreport_get_excelfile_url(){
	global $DB;
	//getting all the records which are having mailsent flag 0.
	$records = $DB->get_records('local_expreport',array('mailsent'=>0));
	foreach ($records as $record) {
		$fs = get_file_storage();
		$fileinfo = new \stdClass;
		$fileinfo->component = 'local_expreport'; 
		$fileinfo->contextid =  context_system::instance()->id;
		$fileinfo->filearea  = 'excelreport';
		$fileinfo->filename = $record->filename;             
		$fileinfo->filepath  = '/';      
		$fileinfo->itemid    = 0;

		$file = $fs->get_file($fileinfo->contextid, $fileinfo->component, $fileinfo->filearea,
			$fileinfo->itemid, $fileinfo->filepath, $fileinfo->filename);

            // Read contents
		if ($file) {
			$contents = $file->get_content();
		} else {
            // file doesn't exist - do something
		}
		//Creationg the report url which will be sent in mail.
		$url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename(), false);

		if(!empty($url)){
			$config = get_config('expreport');
			$sender = get_admin();
			$emailtouser = new \stdClass();
			$emailtouser->id = -1;
			$emailtouser->email = $config->emailid;
			$emailbody ="";                              
			$emailbody .=html_to_text($config->emailbody);          
			$emailbody .="</br>";                     
			$emailbody .=$url;                              
			$mailstatus = email_to_user($emailtouser, $sender, 
				$config->emailsubject, 
				$emailbody,$url);
			//Checking mail status if the mail sent successfully then update the mailsent flag to 1 and mailsent time.
			if($mailstatus){
				$update = new stdClass;
				$update->id = $record->id;
				$update->mailsent = 1;
				$update->mailsenttime = time();
				$DB->update_record('local_expreport',$update);
			}
		}
	}
}