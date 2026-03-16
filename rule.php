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
 * Implementaton of the quizaccess_safeexambrowser plugin.
 *
 * @package   quizaccess_safeexambrowser
 * @copyright 2013 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');


/**
 * A rule requiring the student to promise not to cheat.
 *
 * @copyright 2013 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_safeexambrowser extends quiz_access_rule_base {

    /** @var array the allowed browser exam keys. */
    protected $allowedkeys;

    /** @var array the allowed config keys. */
    protected $allowedconfigkeys;

    /**
     * Create an instance of this rule for a particular quiz.
     *
     * @param quiz $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     */
    public function __construct($quizobj, $timenow) {
        parent::__construct($quizobj, $timenow);
        $this->allowedkeys = self::split_keys($this->quiz->safeexambrowser_allowedkeys);
        $this->allowedconfigkeys = self::split_keys(
                isset($this->quiz->safeexambrowser_allowedconfigkeys)
                ? $this->quiz->safeexambrowser_allowedconfigkeys : '');
    }


    /**
     * Return an appropriately configured instance of this rule, if it is applicable
     * to the given quiz, otherwise return null.
     *
     * @param quiz $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from
     *      time limits by the mod/quiz:ignoretimelimits capability.
     * @return quiz_access_rule_base|null the rule, if applicable, else null.
     */
    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        $quiz = $quizobj->get_quiz();
        $haskeys = !empty($quiz->safeexambrowser_allowedkeys);
        $hasconfigkeys = !empty($quiz->safeexambrowser_allowedconfigkeys);

        if (!$haskeys && !$hasconfigkeys) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    /**
     * Add any fields that this rule requires to the quiz settings form. This
     * method is called from {@link mod_quiz_mod_form::definition()}, while the
     * security section is being built.
     *
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
    public static function add_settings_form_fields(
            mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('textarea', 'safeexambrowser_allowedkeys',
                get_string('allowedbrowserkeys', 'quizaccess_safeexambrowser'),
                array('rows' => 2, 'cols' => 70));
        $mform->setType('safeexambrowser_allowedkeys', PARAM_RAW_TRIMMED);
        $mform->setAdvanced('safeexambrowser_allowedkeys',
                get_config('quizaccess_safeexambrowser', 'allowedkeys_adv')
        );
        $mform->addHelpButton('safeexambrowser_allowedkeys',
                'allowedbrowserkeys', 'quizaccess_safeexambrowser');

        $mform->addElement('textarea', 'safeexambrowser_allowedconfigkeys',
                get_string('allowedconfigkeys', 'quizaccess_safeexambrowser'),
                array('rows' => 2, 'cols' => 70));
        $mform->setType('safeexambrowser_allowedconfigkeys', PARAM_RAW_TRIMMED);
        $mform->setAdvanced('safeexambrowser_allowedconfigkeys',
                get_config('quizaccess_safeexambrowser', 'allowedkeys_adv')
        );
        $mform->addHelpButton('safeexambrowser_allowedconfigkeys',
                'allowedconfigkeys', 'quizaccess_safeexambrowser');
    }

    /**
     * Validate the data from any form fields added using {@link add_settings_form_fields()}.
     *
     * @param array $errors the errors found so far.
     * @param array $data the submitted form data.
     * @param array $files information about any uploaded files.
     * @param mod_quiz_mod_form $quizform the quiz form object.
     * @return array $errors the updated $errors array.
     */
    public static function validate_settings_form_fields(array $errors,
            array $data, $files, mod_quiz_mod_form $quizform) {

        if (!empty($data['safeexambrowser_allowedkeys'])) {
            $keyerrors = self::validate_keys($data['safeexambrowser_allowedkeys']);
            if ($keyerrors) {
                $errors['safeexambrowser_allowedkeys'] = implode(' ', $keyerrors);
            }
        }

        if (!empty($data['safeexambrowser_allowedconfigkeys'])) {
            $keyerrors = self::validate_config_keys($data['safeexambrowser_allowedconfigkeys']);
            if ($keyerrors) {
                $errors['safeexambrowser_allowedconfigkeys'] = implode(' ', $keyerrors);
            }
        }

        return $errors;
    }

    /**
     * Save any submitted settings when the quiz settings form is submitted. This
     * is called from {@link quiz_after_add_or_update()} in lib.php.
     *
     * @param object $quiz the data from the quiz form, including $quiz->id
     *      which is the id of the quiz being saved.
     */
    public static function save_settings($quiz) {
        global $DB;

        $haskeys = !empty($quiz->safeexambrowser_allowedkeys);
        $hasconfigkeys = !empty($quiz->safeexambrowser_allowedconfigkeys);

        if (!$haskeys && !$hasconfigkeys) {
            $DB->delete_records('quizaccess_safeexambrowser', array('quizid' => $quiz->id));
        } else {
            $record = $DB->get_record('quizaccess_safeexambrowser', array('quizid' => $quiz->id));
            if (!$record) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->allowedkeys = $haskeys ? self::clean_keys($quiz->safeexambrowser_allowedkeys) : '';
                $record->allowedconfigkeys = $hasconfigkeys ? self::clean_keys($quiz->safeexambrowser_allowedconfigkeys) : null;
                $DB->insert_record('quizaccess_safeexambrowser', $record);
            } else {
                $record->allowedkeys = $haskeys ? self::clean_keys($quiz->safeexambrowser_allowedkeys) : '';
                $record->allowedconfigkeys = $hasconfigkeys ? self::clean_keys($quiz->safeexambrowser_allowedconfigkeys) : null;
                $DB->update_record('quizaccess_safeexambrowser', $record);
            }
        }
    }


    /**
     * Delete any rule-specific settings when the quiz is deleted. This is called
     * from {@link quiz_delete_instance()} in lib.php.
     *
     * @param object $quiz the data from the database, including $quiz->id
     *      which is the id of the quiz being deleted.
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_safeexambrowser', array('quizid' => $quiz->id));
    }

    /**
     * Return the bits of SQL needed to load all the settings from all the access
     * plugins in one DB query. The easiest way to understand what you need to do
     * here is probalby to read the code of {@link quiz_access_manager::load_settings()}.
     *
     * If you have some settings that cannot be loaded in this way, then you can
     * use the {@link get_extra_settings()} method instead, but that has
     * performance implications.
     *
     * @param int $quizid the id of the quiz we are loading settings for. This
     *     can also be accessed as quiz.id in the SQL. (quiz is a table alisas for {quiz}.)
     * @return array with three elements:
     *     1. fields: any fields to add to the select list. These should be alised
     *        if neccessary so that the field name starts the name of the plugin.
     *     2. joins: any joins (should probably be LEFT JOINS) with other tables that
     *        are needed.
     *     3. params: array of placeholder values that are needed by the SQL. You must
     *        used named placeholders, and the placeholder names should start with the
     *        plugin name, to avoid collisions.
     */
    public static function get_settings_sql($quizid) {
        return array(
            'safeexambrowser.allowedkeys AS safeexambrowser_allowedkeys, ' .
            'safeexambrowser.allowedconfigkeys AS safeexambrowser_allowedconfigkeys',
            'LEFT JOIN {quizaccess_safeexambrowser} safeexambrowser ON safeexambrowser.quizid = quiz.id',
            array());
    }

    /**
     * Whether the user should be blocked from starting a new attempt or continuing
     * an attempt now.
     *
     * @return string false if access should be allowed, a message explaining the
     *      reason if access should be prevented.
     */
    public function prevent_access() {
        if (!self::check_access($this->allowedkeys, $this->allowedconfigkeys,
                $this->quizobj->get_context())) {
            return self::get_blocked_user_message();
        } else {
            return false;
        }
    }

    /**
     * Information, such as might be shown on the quiz view page, relating to this restriction.
     * There is no obligation to return anything. If it is not appropriate to tell students
     * about this rule, then just return ''.
     *
     * @return mixed a message, or array of messages, explaining the restriction
     *         (may be '' if no message is appropriate).
     */
    public function description() {
        return self::get_blocked_user_message();
    }

    /**
     * Get the list of allowed browser keys for the quiz we are protecting.
     *
     * @return array of string, the allowed keys.
     */
    public function get_allowed_keys() {
        return $this->allowedkeys;
    }

    /**
     * Sets up the attempt (review or summary) page with any special extra
     * properties required by this rule.
     *
     * @param moodle_page $page the page object to initialise.
     */
    public function setup_attempt_page($page) {
        $page->set_title($this->quizobj->get_course()->shortname . ': ' . $page->title);
        $page->set_cacheable(false);
        $page->set_popup_notification_allowed(false); // Prevent message notifications.
        $page->set_heading($page->title);
        $page->set_pagelayout('secure');
    }

    /**
     * Generate the message that tells users they must use the secure browser.
     */
    public static function get_blocked_user_message() {
        $url = get_config('quizaccess_safeexambrowser', 'downloadlink');
        if ($url) {
            $a = new stdClass();
            $a->link = html_writer::link($url, $url);
            return get_string('safebrowsermustbeusedwithlink', 'quizaccess_safeexambrowser', $a);
        } else {
            return get_string('safebrowsermustbeused', 'quizaccess_safeexambrowser');
        }
    }

    /**
     * This helper method takes list of keys in a string and splits it into an
     * array of separate keys.
     * @param string $keys the allowed keys.
     * @return array of string, the separate keys.
     */
    public static function split_keys($keys) {
        $keys = preg_split('~[ \t\n\r,;]+~', $keys, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($keys as $i => $key) {
            $keys[$i] = strtolower($key);
        }
        return $keys;
    }

    /**
     * This helper method validates a string containing a list of keys.
     * @param string $keys a list of keys separated by newlines.
     * @return array list of any problems found.
     */
    public static function validate_keys($keys) {
        $errors = array();
        $keys = self::split_keys($keys);
        $uniquekeys = array();
        foreach ($keys as $i => $key) {
            if (!preg_match('~^[a-f0-9]{64}$~', $key)) {
                $errors[] = get_string('allowedbrowserkeyssyntax', 'quizaccess_safeexambrowser');
                break;
            }
        }
        if (count($keys) != count(array_unique($keys))) {
            $errors[] = get_string('allowedbrowserkeysdistinct', 'quizaccess_safeexambrowser');
        }
        return $errors;
    }

    /**
     * This helper method validates a string containing a list of config keys.
     * @param string $keys a list of config keys separated by newlines.
     * @return array list of any problems found.
     */
    public static function validate_config_keys($keys) {
        $errors = array();
        $keys = self::split_keys($keys);
        foreach ($keys as $i => $key) {
            if (!preg_match('~^[a-f0-9]{64}$~', $key)) {
                $errors[] = get_string('allowedconfigkeyssyntax', 'quizaccess_safeexambrowser');
                break;
            }
        }
        if (count($keys) != count(array_unique($keys))) {
            $errors[] = get_string('allowedconfigkeysdistinct', 'quizaccess_safeexambrowser');
        }
        return $errors;
    }

    /**
     * This helper method takes a set of keys that would pass the slighly relaxed
     * validation peformed by {@link validate_keys()}, and cleans it up so that
     * the allowed keys are lower case and separated by a single newline character.
     * @param string $keys the allowed keys.
     * @return string a cleaned up version of the $keys string.
     */
    public static function clean_keys($keys) {
        return implode("\n", self::split_keys($keys));
    }

    /**
     * Check the whether the current request is permitted.
     * @param array $keys allowed browser exam keys.
     * @param array $configkeys allowed config keys.
     * @param context $context the context in which we are checking access. (Normally the quiz context.)
     * @return bool true if the user is using a browser with a permitted key, false if not,
     * or of the user has the 'quizaccess/safeexambrowser:exemptfromcheck' capability.
     */
    public static function check_access(array $keys, array $configkeys, context $context) {
        if (has_capability('quizaccess/safeexambrowser:exemptfromcheck', $context)) {
            return true;
        }

        $url = self::get_this_page_url();

        // Check Config Key header first (platform-independent, preferred).
        if (!empty($configkeys) &&
                array_key_exists('HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH', $_SERVER)) {
            if (self::check_keys($configkeys, $url,
                    trim($_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH']))) {
                return true;
            }
        }

        // Fall back to Browser Exam Key header.
        if (!empty($keys) &&
                array_key_exists('HTTP_X_SAFEEXAMBROWSER_REQUESTHASH', $_SERVER)) {
            if (self::check_keys($keys, $url,
                    trim($_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the full URL that was used to request the current page, which is
     * what we need for verifying the X-SafeExamBrowser-RequestHash header.
     */
    public static function get_this_page_url() {
        global $FULLME;
        return $FULLME;
    }

    /**
     * Check the hash from the request header against the permitted keys.
     * @param array $keys allowed keys.
     * @param string $url the request URL.
     * @param string $header the value of the X-SafeExamBrowser-RequestHash to check.
     * @return bool true if the hash matches.
     */
    public static function check_keys(array $keys, $url, $header) {
        foreach ($keys as $key) {
            if (self::check_key($key, $url, $header)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check the hash from the request header against a single permitted key.
     * @param string $key an allowed key.
     * @param string $url the request URL.
     * @param string $header the value of the X-SafeExamBrowser-RequestHash to check.
     * @return bool true if the hash matches.
     */
    public static function check_key($key, $url, $header) {
        return hash('sha256', $url . $key) === $header;
    }
}
