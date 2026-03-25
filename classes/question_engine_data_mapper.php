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
 * This file defines the quiz randomsummary question engine data mapper.
 *
 * @package   quiz_randomsummary
 * @copyright 2015 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_randomsummary;
use qubaid_condition;
use question_engine_data_mapper as base_question_engine_data_mapper;

/**
 * Modified version of load_questions_usages_question_state_summary() to obtain summary of responses to questions.
 *
 * @copyright 2015 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_engine_data_mapper extends base_question_engine_data_mapper {
    /**
     * Modified version of load_questions_usages_question_state_summary() to obtain summary of responses to questions.
     *
     * This method may be called publicly.
     *
     * @param qubaid_condition $qubaids used to restrict which usages are included
     * in the query. See qubaid_condition class.
     * @param array $slots A list of slots for the questions you want to konw about.
     * @return array The array keys are slot,qestionid. The values are objects with
     * fields $slot, $questionid, $inprogress, $name, $needsgrading, $autograded,
     * $manuallygraded and $all.
     */
    public function load_questions_usages_question_state_summary(
        qubaid_condition $qubaids,
        $slots = null
    ) {

        $rs = $this->db->get_recordset_sql("
          SELECT qa.questionid,
               q.name,
               qas.state,
               COUNT(1) AS numstate

           FROM {$qubaids->from_question_attempts('qa')}
           JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
               AND qas.sequencenumber = {$this->latest_step_for_qa_subquery()}
           JOIN {question} q ON q.id = qa.questionid

          WHERE {$qubaids->where()}

          GROUP BY
            qa.questionid,
            q.name,
            q.id,
            qas.state

          ORDER BY
           qa.questionid,
           q.name,
           q.id
           ", $qubaids->from_where_params());

        $results = [];
        foreach ($rs as $row) {
            if (!array_key_exists($row->questionid, $results)) {
                $res = (object) [
                    'questionid' => $row->questionid,
                    'name' => $row->name,
                    'all' => 0,
                ];
                $results[$row->questionid] = $res;
            }
            $results[$row->questionid]->{$row->state} = $row->numstate;

            $results[$row->questionid]->all += $row->numstate;
        }
        $rs->close();

        return $results;
    }

    /**
     * Load the average mark, and number of attempts, for each question.
     *
     * @param qubaid_condition $qubaids used to restrict which usages are included
     * in the query.
     * @return array of objects with fields ->questionid, ->averagefraction and ->numaveraged.
     */
    public function load_questions_average_marks(qubaid_condition $qubaids) {

        return $this->db->get_records_sql("
               SELECT   qa.questionid,
                        AVG(COALESCE(qas.fraction, 0)) AS averagefraction,
                        COUNT(1) AS numaveraged

                FROM    {$qubaids->from_question_attempts('qa')}
                JOIN    {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                        AND qas.sequencenumber = {$this->latest_step_for_qa_subquery()}

               WHERE    {$qubaids->where()}

            GROUP BY    qa.questionid

            ORDER BY qa.questionid", $qubaids->from_where_params());
    }
}
