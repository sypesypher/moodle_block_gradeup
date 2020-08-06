<?php
// This file is part of Moodle - http://moodle.org/
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
 * Gradeup Block.
 *
 * @package   block_gradeup
 * @author    Chris Strothman	<cstrothman@southern.edu>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_gradeup extends block_base {

	/** @var boolean This variable checks if js is loaded */
    private $jsloaded = false;

	public function init() {
		global $CFG, $USER, $DB, $USER;

        $this->title = get_string('gradeup', 'block_gradeup');
    }
	
	

	public function get_content() {

		global $CFG, $USER, $DB, $USER;

		if ($this->content !== null) {
		  return $this->content;
		}
	 
		$this->content       =  new stdClass;

		//pull in grades: currently just grab file
		//$this->content->text .= '<script src="/blocks/gradeup/gradeupjs/grades.js"></script>';

		//pull grades
		$courses = enrol_get_users_courses($USER->id, true);
		$this->content->text .= '<br>Your Courses: ';

		//user selects which course to pull data from
		$dropdownCourses = [];
		foreach ($courses as $course) {
			$dropdownCourses;
		}

		foreach ($courses as $course) {
			if ($course->id == 2) { //TODO: the "2" is just a placeholder until the user can select which course they want to display
				$this->content->text .= $course->fullname . ': ' . $course->id . '<br>' ;

				//Get the total points in a course to calculate weights of assignments
				$getTotalCoursePoints = "SELECT SUM(grade) as totalPoints FROM mdl_assign a WHERE a.course=". $course->id ."; ";
				$totalCoursePoints = $DB->get_records_sql($getTotalCoursePoints);
				$totalPoints = key($totalCoursePoints);
				
				//Get user grades from the moodle database
				$getUserGrades = "SELECT q1.itemname, q1.finalgrade, q1.grademax, q1.duedate as due,q2.averagegrade FROM (
									SELECT gi.itemname, g.finalgrade, gi.grademax, a.duedate 
										FROM mdl_grade_grades g 
										INNER JOIN mdl_grade_items gi ON gi.id = g.itemid 
										INNER JOIN mdl_assign a ON a.name=gi.itemname 
										WHERE g.userid = ". $USER->id ." AND gi.courseid = ". $course->id ." AND gi.itemname IS NOT NULL 
										ORDER BY a.duedate
									) q1 INNER JOIN (
										SELECT gi.itemname, AVG(finalgrade) as averageGrade
										FROM mdl_grade_grades g 
										INNER JOIN mdl_grade_items gi ON gi.id = g.itemid 
										INNER JOIN mdl_assign a ON a.name=gi.itemname 
										WHERE gi.courseid = ". $course->id ." AND gi.itemname IS NOT NULL 
										GROUP BY itemname
										ORDER BY gi.itemname
									) q2 ON q1.itemname=q2.itemname ORDER BY q1.duedate"; 
				$student_grades = $DB->get_records_sql($getUserGrades);
				//take the database results and format into a PHP grades Object array 
				foreach ($student_grades as $grade) {
					$grade->weight = $grade->grademax / $totalPoints * 100; //calculate the weight of an assignment as a value out of 100
					if ($grade->finalgrade == null){
						$grade->score =  null;
						$grade->originalScore = null;
					} else {
						$grade->score =  ($grade->finalgrade) / ($grade->grademax);
						$grade->originalScore = $grade->score;
					}
					if ($grade->averagegrade == null) {
						$grade->averageScore = null;
					} else {
						$grade->averageScore = ($grade->averagegrade) / ($grade->grademax);
					}
					
				}
				
				//convert php grades objects array to a string (in JSON format) so it can be passed to the javascript, is there a better way? probably
				$jsonGradesString = "let grades = [";
				foreach ($student_grades as $grade){
					$jsonGradesString .= "{";
					$jsonGradesString .= "itemname: \"" . $grade->itemname . "\",";
					$jsonGradesString .= "weight: " . $grade->weight . ",";

					if ($grade->score == null) {
						$jsonGradesString .= "score: null,";
						$jsonGradesString .= "originalScore: null,";
					} else {
						$jsonGradesString .= "score: " . $grade->score . ",";
						$jsonGradesString .= "originalScore: " . $grade->score . ",";
					}

					if ($grade->averageScore == null) {
						$jsonGradesString .= "averageScore: null,";
					} else {
						$jsonGradesString .= "averageScore: " . $grade->averageScore . ",";
					}
					
					
					$jsonGradesString .= "due: " . $grade->due;
					$jsonGradesString .= "}";
					$jsonGradesString .= ",";
				}
				$jsonGradesString = rtrim($jsonGradesString, ","); //remove the comma after the last grade
				$jsonGradesString .= "];";
				
				//pass the json 
				$this->content->text .= '<script>';
				$this->content->text .= $jsonGradesString;
				$this->content->text .= '</script>';
			}
		}

		$this->content->text .= '<script src="https://cdn.jsdelivr.net/npm/@svgdotjs/svg.js@3.0/dist/svg.min.js"></script>'; //SVG.js
		$this->content->text .= '<script src="/blocks/gradeup/gradeupjs/heatmap.js"></script>';
		$this->content->text .= '<script src="/blocks/gradeup/gradeupjs/burnup.js"></script>';
		$this->content->text .= '<div id="svgContainer"></div>';
		$this->content->text .= '<div id="svgContainer2"></div>';

		//Define all the needed String values reading from lang directory
		$this->content->text .= '<script>';
		$this->content->text .= 'let promptString = \'' . get_string('promptString', 'block_gradeup') . '\';';
        $this->content->text .= 'let gradeString = \'' . get_string('gradeString', 'block_gradeup') . '\';';
        $this->content->text .= 'let weightString = \'' . get_string('weightString', 'block_gradeup') . '\';';
        $this->content->text .= 'let averageString = \'' . get_string('averageString', 'block_gradeup') . '\';';
        $this->content->text .= 'let maxGradeString = \'' . get_string('maxGradeString', 'block_gradeup') . '\';';
        $this->content->text .= 'let projectionGradeString = \'' . get_string('projectionGrade', 'block_gradeup') . '\';';
        $this->content->text .= 'let legendMinString =  \'' . get_string('legendMin', 'block_gradeup') . '\';';
        $this->content->text .= 'let legendAverageString = \'' . get_string('legendAverage', 'block_gradeup') . '\';';
        $this->content->text .= 'let potentialGradeString = \'' . get_string('potentialGradeString', 'block_gradeup') . '\';';
        $this->content->text .= 'let predictedGradeString = \'' . get_string('legendPredict', 'block_gradeup') . '\';';
		$this->content->text .= 'let legendString = \'' . get_string('legendLabel', 'block_gradeup') . '\';';
        $this->content->text .= 'let resetButtonString = \'' . get_string('resetButton', 'block_gradeup') . '\';';
        $this->content->text .= 'let heatMapString = \'' . get_string('heatmapLabel', 'block_gradeup') . '\';';

        $this->content->text .= 'function getData() {'; //Normally a call to get data, but this will do for an example
		$this->content->text .=     'let data = grades;';
        $this->content->text .=     'return data;';
        $this->content->text .= '};';
		$this->content->text .= 'let data = getData();';
		$this->content->text .= 'let scale = 600;';
		$this->content->text .= 'var draw = SVG().addTo(\'#svgContainer\').size(scale+500,scale+100);'; //additional area for chart legend and assignment names
		$this->content->text .= 'drawChart(scale,draw);';
		$this->content->text .= 'drawAssignments(scale, draw,data);';
		$this->content->text .= 'var draw2 = SVG().addTo(\'#svgContainer2\').size(scale+scale*.66,scale/2);';
		$this->content->text .= 'drawHeatMap(scale,draw2,data);';
		$this->content->text .= '</script>';

		return $this->content;
	}
		
}

