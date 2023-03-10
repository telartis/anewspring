<?php declare(strict_types=1);

/**
 * Project:     aNewSpring API PHP Client
 * File:        anewspring.php
 * @author      Jeroen de Jong <jeroen@telartis.nl>
 * @copyright   2021-2023 Telartis BV
 * @link        https://demo.anewspring.nl/apidocs
 * @version     5.3.6
 *
 *
 * Usage:
 * $anewspring = new \telartis\anewspring\anewspring();
 * $user = $anewspring->getUser($uid);
 *
 *
 * //////////////// Functions ////////////////
 *
 * query(string $method, string $path, string $result_type = 'string|json|json:rkey:type:column|http_code', array $data = [], int $data_count = 0): mixed
 * check_environment(): string
 * data_string(array $data): string
 * clean_html(string $html): string
 * dbg($data, string $name = ''): string
 * hide_pass(string $text): string
 * mail_error($error, $content = ''): void
 * log_entry(string $method, string $path, string $data_string, int $http_code, string $msg): array
 * log_error(string $method, string $path, string $data_string, int $http_code, string $msg): void, Log error message to $this->error_log
 * out(string $msg): void, Echoed message or added to $this->output
 * out_stats(int $count, string $single, string $plural, string $info = ''): void, Echoed statistics count or added to $this->output
 *
 *
 * //////////////// aNewSpring Functions ////////////////
 *
 * *** users : User management ***
 * userExists(int $uid): bool (also checks $this->user_ids)
 * getUser(int $uid, bool $add_groups = true): array
 * getUsers(): array
 * initializeId(string $login, int $uid): int http_code
 * addUser(int $uid, array $user): int http_code
 * updateUser(int $uid, array $user): int http_code
 * addOrUpdateUser(int $uid, array $user): int http_code
 * addUserRoles(int $uid, array $roles, bool $ignoreDuplicates = true): int http_code
 * deleteUserRoles(int $uid, array $roles): int http_code (TBD: this API-function does not exist)
 * deleteUser(int $uid): int http_code
 *
 * *** groups : (User) group management ***
 * groupExists(string $groupID): bool
 * getGroup(string $groupID): array (TBD: this API-function does not exist)
 * getGroups(): array (TBD: this API-function does not exist)
 * addGroup(string $groupID, string $type, string $name, string $parent = ''): int http_code
 * updateGroup(string $groupID, string $name = '', string $parent = ''): int http_code
 * groupUserExists(string $groupID, int $uid): bool (also checks $this->group_users)
 * getGroupUsers(string $groupID, string $column = 'id|array'): array
 * addGroupUsers(string $groupID, int $uid): int http_code
 * deleteGroupUsers(string $groupID, int $uid, bool $ignoreMissing = true): int http_code
 * deleteGroup(string $groupID, bool $force = false): int http_code
 *
 * *** sso : Single sign-on ***
 * getLoginToken(int $uid): string
 *
 * *** subscriptions : (Course) subscription management ***
 * isSubscribed(int $uid, string $courseID): bool (also checks $this->subscriptions)
 * getSubscriptions(int $uid, bool $skipStats = true, string $column = 'id|array'): array
 * getResults(int $uid, bool $asStudent = true): array
 *
 * *** courses : Course (instance) management ***
 * getTemplates(bool $includeWithoutId = false): array
 * getCourses(string $templateID): array
 * getCourse(string $id): array
 * getStudentSubscriptions(string $courseID, bool $skipStats = true, string $column = 'id|array|count'): mixed
 * getStudentTeachers(int $studentID, string $courseID, bool $excludeTeacherGroups = false, string $column = 'id|array'): array
 * getTeacherStudents(int $teacherID, string $courseID, string $column = 'id|array'): array
 * getCoursePermissions(string $courseID, int $teacherID): array (TBD: this API-function does not exist)
 * setCoursePermissions(string $courseID, int $teacherID, array $permission, bool $unset = false): int
 * addTeacherStudents(string $courseID, int $teacherID, array $students, bool $ignoreDuplicates = true): int http_code
 * deleteTeacherStudents(string $courseID, int $teacherID, array $students, bool $ignoreMissing = true): int http_code
 *
 *
 * //////////////// Extra Functions ////////////////
 *
 * is_mobile_app(): bool
 * user_fields(string $function = ''): array
 * user_status(int $uid): string 'active', 'inactive', 'nonexisting' or 'error'
 * user_has_started(int $uid): bool
 * all_groups(): array string groupIDs
 * get_groups(int $uid): array, e.g.: ['student', 'teacher']
 * init_user_ids(): void, $this->user_ids, $this->user_ids_active, $this->user_ids_inactive filled
 * init_group_users(): void, $this->group_users filled
 * init_course_ids(): void, $this->course_ids filled
 * init_subscriptions(): void, $this->subscriptions filled
 *
 *
 */

namespace telartis\anewspring;

class anewspring
{
    public $api_key  = '';
    public $base_url = 'https://demo.anewspring.nl/api';
    public $log_file = '/var/log/anewspring.log'; // leave empty if you do not want logging

    public $mobile_user_agent = 'aNewSpring/APP_NAME/';

    public $error     = false;
    public $http_code = 0;
    public $message   = '';    // Response error string
    public $info;              // cURL response info object

    public $stdout = false;
    public $output = '';
    public $timer_last = 0;
    public $api_calls  = [];
    public $http_codes = [];
    public $call_prev  = [];
    public $call_cur   = [];
    public $error_log  = '';

    public $user_ids = [];           // All User IDs that exists on the aNewSpring server
    public $user_ids_active = [];    // All User IDs that exists on the aNewSpring server and are active
    public $user_ids_inactive = [];  // All User IDs that exists on the aNewSpring server and are inactive
    public $group_users = [];        // All User IDs per groupID
    public $course_ids = [];         // All courseIDs that exists on the aNewSpring server
    public $subscriptions = [];      // All subscribed student User IDs per courseID
    public $relationships = [];      // Counts Teacher/Student-relationships per courseID. Each row is an array of [reladd, reldel, total]
    public $total_companies_managed_by_teachers = 0;

    public $groups = []; // job_titleID => groupID
    public $group_teacher = 'TCH';


    /////////////////////////////////////////// Functions ///////////////////////////////////////////

    /**
     * Query API
     *
     * @link   https://support.anewspring.com/nl/articles/70412
     *
     * @param  string   $method       HTTP verb to perform the request with: GET, POST, PUT, DELETE
     * @param  string   $path
     * @param  string   $result_type  Optional, 'string' (default), 'json', 'json:rkey:type:column' or 'http_code'
     * @param  array    $data         Optional, default []
     * @param  integer  $data_count   Optional, default 0
     * @return mixed    result_type:
     *                  string                     string
     *                  json                       array
     *                  json:result:string         array[result]=>string
     *                  json:result:bool           array[result]=>bool
     *                  json:users:array           array[users]=>array
     *                  json:users:array:id        array[users]=>array of int
     *                  json:students:array:count  count(array[students])=>int
     *                  http_code                  int
     */
    public function query(string $method, string $path, string $result_type = 'string', array $data = [], int $data_count = 0)
    {
        $name = explode('/', $path)[0];
        if (!array_key_exists($name, $this->api_calls)) {
            $this->api_calls[$name] = 1;
        } else {
            $this->api_calls[$name]++;
        }

        $data_string = $this->data_string($data);
        unset($data);

        $errmsg = $this->check_environment();
        if (!empty($errmsg)) {
            $this->mail_error('Error '.$path.' '.$errmsg);
            $this->http_code = 500;
        } else {
            $url = $this->base_url.'/'.$path;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT,      'Mozilla/4.0 (compatible; Telartis/aNewSpring API Client; '.
                php_uname('s').'; PHP/'.phpversion().')');
            curl_setopt($ch, CURLOPT_HTTPHEADER,     ['X-API-Key: '.$this->api_key]);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  $method);
            if (strlen($data_string)) {
                // The Content-Type header will be set to multipart/form-data.
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            }
            $result_string = curl_exec($ch);
            $this->info = (object) curl_getinfo($ch);
            if ($result_string === false) {
                $result_string = 'curl_exec error: '.curl_error($ch).' ('.curl_errno($ch).')';
                $this->mail_error('Error '.$path.' '.$result_string, $this->dbg($this->info, 'curl_getinfo'));
            }
            $this->http_code = (int) $this->info->http_code;
            // if ($this->http_code != 200) $this->mail_error('aNewSpring debug', $this->dbg($result_string, 'result_string')."\n".$this->dbg($this->info, 'curl_getinfo'));
        }

        if (!array_key_exists($this->http_code, $this->http_codes)) {
            $this->http_codes[$this->http_code] = 1;
        } else {
            $this->http_codes[$this->http_code]++;
        }

        if ($this->http_code == 200) {
            $this->error = false;
            $this->message = 'OK';
        } else {
            if (is_string($result_string)) {
                $result_string = $this->clean_html($result_string);
            } else {
                $result_string = $this->dbg($result_string);
            }
            $this->error = true;
            $this->message = 'Error '.$path.' '.$result_string;
        }

        [$result_type, $rkey, $rtype, $column] = explode(':', $result_type);

        if ($result_type == 'json' && $this->http_code == 200) {
            $result = json_decode($result_string, true);
        } elseif ($result_type == 'http_code') {
            $result = $this->http_code;
        } else { // string / default / error
            $result = $result_string;
        }


        // Get string value used in log entry:
        if ($result_type == 'http_code') {
            $val = is_string($result_string) ? $result_string : $this->dbg($result_string);
        } elseif (is_string($result)) {
            $val = $result;
        } elseif (is_array($result)) {
            if (!empty($rkey) && isset($result[$rkey])) {
                $val = 'array['.$rkey.']=>';
                if (is_array($result[$rkey])) {
                    $val .= 'array('.count($result[$rkey]).')';
                } elseif (is_bool($result[$rkey])) {
                    $val .= $result[$rkey] ? 'true' : 'false';
                } elseif (is_string($result[$rkey])) {
                    $val .= 'string('.strlen($result[$rkey]).')';
                } else {
                    $val .= $this->dbg($result[$rkey]);
                }
            } else {
                $val = 'array('.count($result).')';
            }
        } else {
            $val = $this->dbg($result);
        }
        if ($this->http_code == 200) {
            $val = 'OK'.($val ? ' '.$val : '');
        } elseif (substr($val, 0, 4) == $this->http_code.' ') { // Is the HTTP-code part of the result message?
            $val = substr($val, 4); // Remove HTTP-code
        }
        $val = str_replace(["\n", "\r"], [' ', ''], $val); // Replace new lines with spaces, to make sure all values are on one line.


        // Log this query call:
        $log_entry = $this->log_entry(
            $method,
            $path,
            ($data_count ? $data_count.':' : '').$data_string,
            $this->http_code,
            $val // result value
        );

        if (!empty($this->log_file)) {
            @file_put_contents(
                $this->log_file,
                implode("\t", $log_entry)."\n",
                FILE_APPEND
            );
        }

        $this->call_prev = $this->call_cur;
        $this->call_cur  = $log_entry;
        if ($this->http_code != 200) {
            $this->error_log .= trim(implode(' ', $this->call_prev))."\n";
            $this->error_log .= trim(implode(' ', $this->call_cur ))."\n";
        }


        if ($result_type == 'json') {
            // Result is of wrong type?
            if (empty($rkey) ? !is_array($result)
                : !isset($result[$rkey]) || get_debug_type($result[$rkey]) != $rtype
            ) {
                $expected = empty($rkey) ? 'array' : "array[$rkey]=>$rtype";
                $this->log_error($method, $path, $data_string,
                    $this->http_code, "Error: $expected expected, but got ".$this->dbg($result)
                );
                if ($rtype == 'bool') {
                    $result = false;
                } elseif ($column == 'count') {
                    $result = -1;
                } elseif (empty($rkey) || $rtype == 'array') {
                    $result = [];
                } else {
                    $result = '';
                }
            } else { // OK: no type errors
                if (!empty($rkey)) {
                    $result = $result[$rkey];
                }
                if ($column == 'count') {
                    $result = count($result);
                } elseif (!empty($column) && $column != 'array') {
                    $result = array_column($result, $column);
                }
            }
        }

        return $result;
    }

    /**
     * Check environment variables
     *
     * @return string
     */
    public function check_environment(): string
    {
        return '';
    }

    /**
     * Convert data array to string
     *
     * @param  array    $data
     * @return string
     */
    public function data_string(array $data): string
    {
        $data_string = '';
        if ($data) {
            // Convert booleans to string:
            foreach ($data as $key => $val) if (is_bool($val)) {
                $data[$key] = $val ? 'true' : 'false';
            }
            // Some parameters are indicated as data type Array[string].
            // To send a parameter as an array, you can simply repeat the same parameter multiple times
            // with different values in the body of one API call.
            // Source: https://support.anewspring.com/nl/articles/70408-api-de-apidocs-gebruiken
            // See for example addUserRoles: https://demo.anewspring.com/apidocs#!/users/addUserRoles
            $data_string = preg_replace(
                '/%5B(?:[0-9]|[1-9][0-9]+)%5D=/',   // pattern
                '=',                                // replacement
                http_build_query($data, '', '&')    // subject
            );
        }

        return $data_string;
    }

    /**
     * Clean HTML
     *
     * @param  string   $html
     * @return string
     */
    public function clean_html(string $html): string
    {
        // get contents of body-tag from HTML and strip tags
        $string = strip_tags(preg_replace('|^.*<body[^>]*>|ims', '', preg_replace('|</body>.*$|ims', '', $html)));

        // &nbsp; -> normal space
        $string = str_replace('&nbsp;', ' ', $string);

        // replace non-breaking space characters with normal spaces
        $string = str_replace("\xc2\xa0", ' ', $string);

        // remove multiple spaces
        $string = preg_replace('/ +/', ' ', $string);

        // remove spaces at begin of lines
        $string = preg_replace('/^ +/m', '', $string);

        // remove spaces at end of lines
        $string = preg_replace('/ +$/m', '', $string);

        // remove multiple line feeds
        $string = preg_replace('/\n{3,}/', "\n\n", $string);
        $string = preg_replace('/\n{3,}/', "\n\n", $string);

        return trim($string);
    }

    /**
     * Debug Variable
     *
     * @param  mixed    $data  Variable to output in debug string
     * @param  string   $name  Name of variable
     * @return string
     */
    public function dbg($data, string $name = ''): string
    {
        ob_start();
        var_dump($data);
        $result = trim(ob_get_contents());
        ob_end_clean();
        if (!empty($name)) {
            $result = "$name=[$result]";
        }

        return $this->hide_pass($result);
    }

    /**
     * Remove API password from text
     *
     * @param  string   $text
     * @return string
     */
    public function hide_pass(string $text): string
    {
        return str_replace($this->api_key, '***', $text);
    }

    /**
     * Mail error
     *
     * @param  string   $error
     * @param  string   $content  Optional, default ''
     * @return void
     */
    public function mail_error($error, $content = ''): void
    {
        if (false) mail('webmaster@example.com', $error, $content);
    }

    /**
     * Get log entry array
     *
     * @param  string   $method
     * @param  string   $path
     * @param  string   $data_string
     * @param  integer  $http_code
     * @param  string   $msg
     * @return array
     */
    public function log_entry(string $method, string $path, string $data_string, int $http_code, string $msg): array
    {
        return [
            date('Y-m-d H:i:s'),
            $method,
            $path,
            $data_string,
            $http_code,
            $msg,
        ];
    }

    /**
     * Log error message to $this->error_log
     *
     * @param  string   $method
     * @param  string   $path
     * @param  string   $data_string
     * @param  integer  $http_code
     * @param  string   $msg
     * @return void
     */
    public function log_error(string $method, string $path, string $data_string, int $http_code, string $msg): void
    {
        $log_entry = $this->log_entry($method, $path, $data_string, $http_code, $msg);
        $this->error_log .= trim(implode(' ', $log_entry))."\n";
    }

    /**
     * Echoed message or added to $this->output
     *
     * @param  string   $msg
     * @return void
     */
    public function out(string $msg): void
    {
        if ($this->stdout) {
            echo $msg."\n";
        } else {
            $this->output .= $msg."\n";
        }
    }

    /**
     * Echoed statistics count or added to $this->output
     *
     * @param  integer  $count
     * @param  string   $single
     * @param  string   $plural
     * @param  string   $info    Optional, default ''
     * @return void
     */
    public function out_stats(int $count, string $single, string $plural, string $info = ''): void
    {
        if ($count) {
            if ($count == 1) {
                $name = $single;
            } elseif (strlen($plural) < strlen($single)) {
                $name = $single.$plural;
            } else {
                $name = $plural;
            }
            $this->out(number_format((float) $count, 0, ',', '.').' '.$name.' '.$info);
        }
    }

    /////////////////////////////////////////// aNewSpring Functions ///////////////////////////////////////////

    /**
     * Check if user exists (also checks $this->user_ids)
     *
     * @param  integer  $uid  User ID
     * @return boolean
     */
    public function userExists(int $uid): bool
    {
        if ($this->user_ids) {
            $result = in_array($uid, $this->user_ids);
        } else {
            $result = $this->query('GET', "userExists/$uid", 'json:result:bool');
        }

        return $result;
    }

    /**
     * Get user
     *
     * TBD: It is not possible to see which groups a user is a member of.
     * Add extra groups field. This field is similar to the roles field,
     * but returns all groups that a user is a member of in an array.
     *
     * @param  integer  $uid         User ID
     * @param  boolean  $add_groups  Optional, default TRUE
     * @return array(id,uid,login,email,title,firstName,middleName,lastName,initials,gender,company,function,
     *               dateOfBirth,telephoneNumber,faxNumber,cellPhoneNumber,address,zip,residence,country,
     *               invoiceAddress,invoiceZip,invoiceResidence,invoiceCountry,custom1,custom2,custom3,custom4,custom5,
     *               locale,timeZoneGroup,
     *               active,renewable,roles,groups)
     */
    public function getUser(int $uid, bool $add_groups = true): array
    {
        $result = $this->query('GET', "getUser/$uid", 'json');

        if ($result) {
            $roles = $result['roles'];
            sort($roles);
            $result['roles'] = $roles;

            if (!isset($result['groups']) && $add_groups) {
                $result['groups'] = $this->get_groups($uid);
            }
        }

        return $result;
    }

    /**
     * Get all users
     *
     * @return array(id,uid,name,login,email,active)
     */
    public function getUsers(): array
    {
        return $this->query('GET', 'getUsers', 'json:users:array');
    }

    /**
     * Initialize user ID
     * This can be used to set the ID of an existing user who does not have an ID yet.
     *
     * 200 success
     * 404 no user exists with the specified login
     * 409 the user already has an ID or if the ID is already used
     *
     * @param  string   $login  E-mail address
     * @param  integer  $uid    User ID
     * @return integer  HTTP Code
     */
    public function initializeId(string $login, int $uid): int
    {
        return $this->query('POST', "initializeId/$login/$uid", 'http_code');
    }

    /**
     * Add user
     *
     * 200 success
     * 409 a user with the specified ID already exists | setting force password change is not allowed | user is archived
     *
     *
     * @param  integer  $uid   User ID
     * @param  array    $user  User Row
     * @return integer  HTTP Code
     */
    public function addUser(int $uid, array $user): int
    {
        $fields = $this->user_fields('addUser');
        foreach (array_keys($user) as $field) {
            if (!in_array($field, $fields)) {
                unset($user[$field]);
            }
        }

        return $this->query('POST', "addUser/$uid", 'http_code', $user);
    }

    /**
     * Update user
     *
     * 200 success
     * 409 a user with the specified ID already exists | setting force password change is not allowed | user is archived
     *
     * TBD FIX BUG: It is not possible to set the dateOfBirth to NULL.
     * The dateOfBirth can be empty and it is not required to have a value,
     * but once it has gotten a value it is not optional anymore and cannot be set to NULL again.
     *
     * @param  integer  $uid   User ID
     * @param  array    $user  User Row
     * @return integer  HTTP Code
     */
    public function updateUser(int $uid, array $user): int
    {
        $fields = $this->user_fields('updateUser');
        foreach (array_keys($user) as $field) {
            if (!in_array($field, $fields)) {
                unset($user[$field]);
            }
        }

        return $this->query('POST', "updateUser/$uid", 'http_code', $user);
    }

    /**
     * Add or update user
     *
     * 200 success
     * 409 a user with the specified ID already exists | setting force password change is not allowed | user is archived
     *
     * @param  integer  $uid   User ID
     * @param  array    $user  User Row
     * @return integer  HTTP Code
     */
    public function addOrUpdateUser(int $uid, array $user): int
    {
        $fields = $this->user_fields('addOrUpdateUser');
        foreach (array_keys($user) as $field) {
            if (!in_array($field, $fields)) {
                unset($user[$field]);
            }
        }

        return $this->query('POST', "addOrUpdateUser/$uid", 'http_code', $user);
    }

    /**
     * Add user roles
     *
     * 200 success
     * 404 no user exists with the specified ID
     * 409 a user already has a specified role (and ignoreDuplicates is false)
     *
     * In the API we use old role terms.
     * With student we mean learner, with teacher we mean instructor and with mentor we mean observer.
     *
     * Role (EN):      Rol (NL):           Afkorting:
     * student         Deelnemer           dln
     * teacher         Begeleider          beg
     * mentor          Mentor              ment => observer/Observator/obs
     * author          Auteur              aut
     * designer        Ontwerper           ontw
     * administrator   Beheerder           beh
     * reseller        Reseller            res
     * tenant          Omgevingsbeheerder  omg
     *
     * @param  integer  $uid    User ID
     * @param  array    $roles  See above.
     * @param  boolean  $ignoreDuplicates  Optional, default TRUE. Ignore roles that the user already has.
     * @return integer  HTTP Code
     */
    public function addUserRoles(int $uid, array $roles, bool $ignoreDuplicates = true): int
    {
        return $this->query('POST', "addUserRoles/$uid", 'http_code', [
            'role'             => $roles,
            'ignoreDuplicates' => $ignoreDuplicates,
        ]);
    }

    /**
     * Delete user roles
     *
     * TBD: this API-function does not exist
     *
     * @param  integer  $uid    User ID
     * @param  array    $roles  See addUserRoles
     * @return integer  HTTP Code
     */
    public function deleteUserRoles(int $uid, array $roles): int
    {
        return $this->query('POST', "deleteUserRoles/$uid", 'http_code', [
            'role' => $roles,
        ]);
    }

    /**
     * Delete user
     *
     * 200 success
     * 404 no user exists with the specified ID
     *
     * @param  integer  $uid  User ID
     * @return integer  HTTP Code
     */
    public function deleteUser(int $uid): int
    {
        return $this->query('POST', "deleteUser/$uid", 'http_code');
    }

    /**
     * Check if group exists
     *
     * @param  string   $groupID  The ID of the group in the external system, which is shown as external ID in the platform.
     * @return boolean
     */
    public function groupExists(string $groupID): bool
    {
        return $this->query('GET', "groupExists/$groupID", 'json:result:bool');
    }

    /**
     * Get group
     *
     * TBD: this API-function does not exist
     *
     * @param  string  $groupID  The ID of the group in the external system, which is shown as external ID in the platform.
     * @return array()
     */
    public function getGroup(string $groupID): array
    {
        return $this->query('GET', "getGroup/$groupID", 'json');
    }

    /**
     * Get all groups
     *
     * TBD: this API-function does not exist
     *
     * @return array()
     */
    public function getGroups(): array
    {
        return $this->query('GET', 'getGroups', 'json:groups:array');
    }

    /**
     * Add group
     * This can be used to add a new user group to the learning environment.
     *
     * 200 success
     * 404 no group exists with the specified parent ID
     * 409 a group already exists with the specified ID or the specified parent group is the group itself
     *
     * @param  string   $groupID  The ID of the group in the external system, which is shown as external ID in the platform.
     * @param  string   $type     'student', 'teacher', or 'mentor'
     * @param  string   $name     Group name
     * @param  string   $parent   Optional, default ''. The ID of the parent group in the external system, which is shown as external ID in the platform.
     * @return integer  HTTP Code
     */
    public function addGroup(string $groupID, string $type, string $name, string $parent = ''): int
    {
        $data = [
            'type' => $type,
            'name' => $name,
        ];
        if (!empty($parent)) {
            $data['parent'] = $parent;
        }

        return $this->query('POST', "addGroup/$groupID", 'http_code', $data);
    }

    /**
     * Update group
     * This can be used to update an existing group.
     *
     * 200 success
     * 404 no group exists with the specified parent ID
     * 409 the specified parent group is the group itself
     *
     * @param  string   $groupID  The ID of the group in the external system, which is shown as external ID in the platform.
     * @param  string   $name     Optional, default ''. Group name
     * @param  string   $parent   Optional, default ''. The ID of the parent group in the external system, which is shown as external ID in the platform.
     * @return integer  HTTP Code
     */
    public function updateGroup(string $groupID, string $name = '', string $parent = ''): int
    {
        $data = [];
        if (!empty($name)) {
            $data['name'] = $name;
        }
        if (!empty($parent)) {
            $data['parent'] = $parent;
        }

        return $this->query('POST', "updateGroup/$groupID", 'http_code', $data);
    }

    /**
     * Check if user is member of group
     * This can be used to check if a certain user is a member of a certain group.
     *
     * 200 success
     * 404 no group exists with the specified ID
     *
     * @param  string   $groupID  The ID of the group in the external system, which is shown as external ID in the platform.
     * @param  integer  $uid      The external ID of the user, this parameter can occur multiple times.
     * @return boolean
     */
    public function groupUserExists(string $groupID, int $uid): bool
    {
        $result = false;
        if ($this->group_users) {
            if (array_key_exists($groupID, $this->group_users)) {
                $result = in_array($uid, $this->group_users[$groupID]);
            }
        } else {
            $result = $this->query('GET', "groupUserExists/$groupID/$uid", 'json:result:bool');
        }

        return $result;
    }

    /**
     * Get members of group
     * This can be used to obtain the members of the specified group.
     *
     * 404 no group exists with the specified ID
     *
     * @param  string   $groupID  The ID of the group in the external system, which is shown as external ID in the platform.
     * @param  string   $column   'id' (default) or 'array'
     * @return array    id        array of int User IDs
     *                  array     array(id,uid,name,login,email,active)
     */
    public function getGroupUsers(string $groupID, string $column = 'id'): array
    {
        return $this->query('GET', "getGroupUsers/$groupID", "json:users:array:$column");
    }

    /**
     * Add group users
     * This can be used to add users to an existing user group.
     *
     * 200 success
     * 404 no group exists with the specified parent ID
     * 409 a user does not have the correct role for the type of the group or a user is already in the group (and ignoreDuplicates is false)
     *
     * @param  string   $groupID  The ID of the group in the external system, which is shown as external ID in the platform.
     * @param  integer  $uid      The external ID of the user, this parameter can occur multiple times.
     * @return integer  HTTP Code
     */
    public function addGroupUsers(string $groupID, int $uid): int
    {
        return $this->query('POST', "addGroupUsers/$groupID", 'http_code', [
            'user'                 => $uid,
            'addToTeacherStudents' => false, // Add the student(s) to the 'My Students' list of the teacher(s)
            'ignoreDuplicates'     => true,  // Ignore users who are already in the group
            'notify'               => false, // Send an email to the student to notify them of the new subscription(s)
        ]);
    }

    /**
     * Delete group users
     * This can be used to remove users from an existing user group.
     *
     * 200 success
     * 404 no group exists with the specified ID or a user does not exist with the specified ID (and ignoreMissing is false)
     * 409 a user is not in the group (and ignoreMissing is false)
     *
     * @param  string   $groupID        The ID of the group in the external system, which is shown as external ID in the platform.
     * @param  integer  $uid            The external ID of the user, this parameter can occur multiple times.
     * @param  boolean  $ignoreMissing  Optional, default TRUE. Ignore users who do not exist or are not in the group.
     * @return integer  HTTP Code
     */
    public function deleteGroupUsers(string $groupID, int $uid, bool $ignoreMissing = true): int
    {
        return $this->query('POST', "deleteGroupUsers/$groupID", 'http_code', [
            'user'          => $uid,
            'ignoreMissing' => $ignoreMissing,
        ]);
    }

    /**
     * Delete group
     * This can be used to remove an existing group
     *
     * 200 success
     * 404 no group exists with the specified ID
     * 409 the group is still in use (and force is false)
     *
     * @param  string   $groupID  The ID of the group in the external system, which is shown as external ID in the platform.
     * @param  boolean  $force    Optional default FALSE. Remove the group even if there are users present.
     * @return integer  HTTP Code
     */
    public function deleteGroup(string $groupID, bool $force = false): int
    {
        return $this->query('POST', "deleteGroup/$groupID", 'http_code', [
            'force' => $force,
        ]);
    }

    /**
     * Get Single sign-on (SSO) token, this can be used to log in the user.
     *
     * @link   https://support.anewspring.com/nl/articles/70423
     *
     * @param  integer  $uid  User ID
     * @return string
     */
    public function getLoginToken(int $uid): string
    {
        return $this->query('GET', "getLoginToken/$uid", 'json:result:string');
    }

    /**
     * Check if user is subscribed to course (also checks $this->subscriptions)
     * This can be used to check if a certain user is enroled in a certain course.
     *
     * 200 success
     * 404 no user and/or course exists with the specified ID
     *
     * @param  integer  $uid       The external ID of the user, this parameter can occur multiple times.
     * @param  integer  $courseID  The ID of the course in the external system, which is shown as external ID in the platform.
     * @return boolean
     */
    public function isSubscribed(int $uid, string $courseID): bool
    {
        $result = false;
        if ($this->subscriptions) {
            if (array_key_exists($courseID, $this->subscriptions)) {
                $result = in_array($uid, $this->subscriptions[$courseID]);
            }
        } elseif ($this->userExists($uid)) {
            $result = $this->query('GET', "isSubscribed/$uid/$courseID", 'json:result:bool');
        }

        return $result;
    }

    /**
     * Get subscriptions for user
     * This can be used to obtain the subscriptions of the specified user.
     *
     * 200 success
     * 404 no user exists with the specified ID
     *
     * @param  integer  $uid        The ID of the user in the external system, which is shown as external ID in the platform.
     * @param  boolean  $skipStats  Optional, default TRUE. Don't calculate statistics for performance.
     *                              (progress, knowledgeIntake, efficiency, objectivesProgress)
     * @param  string   $column     'id' (default) or 'array'
     * @return array    id          array of int User IDs
     *                  array       array(id,uid,name,reseller,active,subscribeDate,subscribeDateTime,startDate,startDateTime,
     *                                    finished,expired,progress,knowledgeIntake,efficiency,objectiveProgress)
     */
    public function getSubscriptions(int $uid, bool $skipStats = true, string $column = 'id'): array
    {
        return $this->query('GET', "getSubscriptions/$uid", "json:courses:array:$column", [
            'skipStats' => $skipStats,
        ]);
    }

    /**
     * Get course results for user
     *
     * 404 no user exists with the specified ID, or the user is not subscribed to the course
     *
     * @link   https://demo.anewspring.nl/apidocs#!/subscriptions/getResults
     *
     * @param  integer  $uid        The ID of the user in the external system, which is shown as external ID in the platform.
     * @param  boolean  $asStudent  Optional, default TRUE. Indicates if the results should contain only the results the user
     *                              is allowed to see (meaning only the active course activities and those only if the settings
     *                              of the activity allow it ("Feedback type")
     * @return array(startDate, progress, lastTrainingCompletedDate)
     */
    public function getResults(int $uid, bool $asStudent = true): array
    {
        return $this->query('GET', "getResults/$uid", 'json:courses:array', [
            'asStudent' => $asStudent,
        ]);
    }

    /**
     * Get templates
     * This can be used to obtain all course templates that are published
     * (i.e. all templates for which an instance can be created via the API).
     *
     * @param  boolean  $includeWithoutId  Optional, default FALSE. Include templates without an external ID.
     * @return array(id, uid, name)
     */
    public function getTemplates(bool $includeWithoutId = false): array
    {
        return $this->query('GET', 'getTemplates', 'json:templates:array', [
            'includeWithoutId' => $includeWithoutId,
        ]);
    }

    /**
     * Get courses
     * This can be used to obtain all course instances for a specific course templates
     * that have an external ID (i.e. all instances that can be manipulated via the API)
     *
     * @param  string   $templateID  The ID of the template in the external system, which is shown as external ID in the platform.
     * @return array(id,uid,name,title,description,isTemplate,active,archived,reseller,
     *               type,accessType,startDate,expireDate,expireInterval,teachersAllowed,teacherGroup,mentorGroup)
     */
    public function getCourses(string $templateID): array
    {
        return $this->query('GET', "getCourses/$templateID", 'json:courses:array');
    }

    /**
     * Get course
     * This can be used to obtain the details of the specified course (template). It is a subset of all available details.
     *
     * @param  string   $id  The ID in the external system, which is shown as external ID in the platform.
     * @return array(id,uid,name,title,description,isTemplate,templateUid,active,archived,reseller,
     *               type,accessType,startDate,expireDate,expireInterval,teachersAllowed,teacherGroup,mentorGroup)
     */
    public function getCourse(string $id): array
    {
        return $this->query('GET', "getCourse/$id", 'json');
    }

    /**
     * Get subscriptions for course
     * This can be used to obtain the subscriptions of the specified course.
     *
     * 200 success
     * 404 no course exists with the specified ID
     *
     * @param  string   $courseID   The ID of the course in the external system, which is shown as external ID in the platform.
     * @param  boolean  $skipStats  Optional, default TRUE. Don't calculate statistics (progress, knowledgeIntake, efficiency,
     *                                                      objectivesProgress) for performance.
     * @param  string   $column     'id' (default), 'array' or 'count'
     * @return mixed    id          array of int User IDs
     *                  array       array(id,uid,name,reseller,active,subscribeDate,subscribeDateTime,
     *                                    startDate,startDateTime,finished,expired,progress,
     *                                    knowledgeIntake,efficiency,objectiveProgress)
     *                  count       int total number of subscriptions
     */
    public function getStudentSubscriptions(string $courseID, bool $skipStats = true, string $column = 'id')
    {
        return $this->query('GET', "getStudentSubscriptions/$courseID", "json:students:array:$column", [
            'skipStats' => $skipStats,
        ]);
    }

    /**
     * Get teachers for student in the context of a course
     * This can be used to obtain the teachers of the specified user and course.
     *
     * 404 no user and/or course exists with the specified ID
     *
     * @param  integer  $studentID             The ID of the student in the external system, which is shown as external ID in the platform.
     * @param  string   $courseID              The ID of the course in the external system, which is shown as external ID in the platform.
     * @param  boolean  $excludeTeacherGroups  Optional, default FALSE. Exclude teachers that are linked to student via a group only.
     * @param  string   $column                'id' (default) or 'array'
     * @return array    id                     array of int User IDs
     *                  array                  array(id,uid,name,login,email,active)
     */
    public function getStudentTeachers(int $studentID, string $courseID, bool $excludeTeacherGroups = false, string $column = 'id'): array
    {
        return $this->query('GET', "getStudentTeachers/$studentID/$courseID", "json:users:array:$column", [
            'excludeTeacherGroups' => $excludeTeacherGroups,
        ]);
    }

    /**
     * Get students for teacher in the context of a course
     * This can be used to obtain the students of the specified teacher and course.
     *
     * TBD: If the courseID parameter could occur multiple times, it would speed up the synchronization process significantly.
     * The result needs to be an array indexed on the courseIDs.
     * If you have 100 courses * 1000 teachers, you will need 100k api calls and approximate 5 hours.
     * With this change you only would need 1k api calls and significantly less time to update all Teacher/Student-relationships.
     *
     * @param  integer  $teacherID  The ID of the teacher in the external system, which is shown as external ID in the platform.
     * @param  string   $courseID   The ID of the course in the external system, which is shown as external ID in the platform.
     * @param  string   $column     'id' (default) or 'array'
     * @return array    id          array of int User IDs
     *                  array       array(id,uid,name,login,email,active)
     */
    public function getTeacherStudents(int $teacherID, string $courseID, string $column = 'id'): array
    {
        return $this->query('GET', "getTeacherStudents/$teacherID/$courseID", "json:users:array:$column");
    }

    /**
     * Get course permissions
     *
     * TBD: this API-function does not exist
     *
     * @param  string   $courseID    The ID of the course in the external system, which is shown as external ID in the platform.
     *                               This parameter could occur multiple times.
     * @param  integer  $teacherID   The ID of the teacher in the external system, which is shown as external ID in the platform.
     * @return array    Permission array
     */
    public function getCoursePermissions(string $courseID, int $teacherID): array
    {
        return $this->query('GET', "getCoursePermissions/$courseID/$teacherID", 'json:permission:array');
    }

    /**
     * Set course permissions
     * This can be used to set the course permissions for a specific teacher.
     *
     * @link https://support.anewspring.com/nl/articles/33194
     *
     * Onder Templates > Course > Begeleiders-tabblad zijn deze vijf permissions per teacher instelbaar:
     * CourseSettings                | Uitvoering instellingen | Begeleider mag instellingen van de uitvoering aanpassen.
     * EditCalendar                  | Agenda                  | Begeleider mag agendapunten toevoegen.
     * EditTeacherStudents           | Deelnemers              | Begeleider kan specifieke groep deelnemers maken die hij wil begeleiden.
     * ReceiveStudentMessage         | Berichten               | Begeleider kan berichten versturen en ontvangen van deelnemers.
     * ReceiveCoursePartNotification | Notificaties            | Begeleider ontvangt statistiekenmail, een kopie van de
     *                               |                         | uitvoeringsnotificatie mail van deelnemers en notificaties van discussies
     *                               |                         | en geselecteerde voltooide onderdelen.
     * AssessorPossible              | Beoordelen              | Begeleider mag open vragen, inleveropdrachten en 360° feedback beoordelen.
     *                               |                         | Specificeer dit bij de activiteiten tab.
     *
     * 200 success
     * 404 no course and/or teacher and/or permission exists with the specified ID (or name in the case of permission)
     *
     * @param  string   $courseID    The ID of the course in the external system, which is shown as external ID in the platform.
     * @param  integer  $teacherID   The ID of the teacher in the external system, which is shown as external ID in the platform.
     * @param  array    $permission  The name of the permission, this parameter can occur multiple times.
     * @param  boolean  $unset       Optional, default FALSE. Unset the specified permission(s) instead of set.
     * @return integer  HTTP Code
     */
    public function setCoursePermissions(string $courseID, int $teacherID, array $permission, bool $unset = false): int
    {
        return $this->query('POST', "setCoursePermissions/$courseID/$teacherID", 'http_code', [
            'permission' => $permission,
            'unset'      => $unset,
        ]);
    }

    /**
     * Add teacher students
     * This can be used to add students to the 'My Students' list of a teacher of a course.
     *
     * 200 success
     * 404 no teacher and/or course and/or student exists with the specified ID, or the student is not enroled in the specified course,
     *     or the student/studentUID parameters are not present
     * 409 a student is already on the 'My Students' list of the teacher of the course (and ignoreDuplicates is false)
     *
     * @param  string   $courseID   The ID of the course in the external system, which is shown as external ID in the platform.
     * @param  integer  $teacherID  The ID of the teacher in the external system, which is shown as external ID in the platform.
     * @param  array    $students   The external IDs of the students.
     * @param  boolean  $ignoreDuplicates  Optional, default TRUE. Ignore students who are already on the 'My Students' list
     *                                                             of the specified teacher of the course.
     * @return integer  HTTP Code
     */
    public function addTeacherStudents(string $courseID, int $teacherID, array $students, bool $ignoreDuplicates = true): int
    {
        return $this->query('POST', "addTeacherStudents/$courseID/$teacherID", 'http_code', [
            'student'          => $students,
            'ignoreDuplicates' => $ignoreDuplicates,
        ], count($students));
    }

    /**
     * Delete teacher students
     * This can be used to remove students from the 'My Students' list of a teacher of a course.
     *
     * 200 success
     * 404 no teacher and/or course and/or student exists with the specified ID, or the student is not enroled in the specified course,
     *     or the student/studentUID parameters are not present
     * 409 a student is not on the 'My Students' list of the teacher of the course (and ignoreMissing is false)
     *
     * @param  string   $courseID   The ID of the course in the external system, which is shown as external ID in the platform.
     * @param  integer  $teacherID  The ID of the teacher in the external system, which is shown as external ID in the platform.
     * @param  array    $students   The external IDs of the students.
     * @param  boolean  $ignoreMissing  Optional, default TRUE. Ignore students who are not on the 'My Students' list
     *                                                          of the specified teacher of the course.
     * @return integer  HTTP Code
     */
    public function deleteTeacherStudents(string $courseID, int $teacherID, array $students, bool $ignoreMissing = true): int
    {
        return $this->query('POST', "deleteTeacherStudents/$courseID/$teacherID", 'http_code', [
            'student'       => $students,
            'ignoreMissing' => $ignoreMissing,
        ], count($students));
    }


    /////////////////////////////////////////// Extra Functions ///////////////////////////////////////////

    /**
     * Check User-Agent from super global $_SERVER and see if current user is in the mobile app
     *
     * @return boolean
     */
    public function is_mobile_app(): bool
    {
        $user_agent = (string) filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_STRING);

        return strpos($user_agent, $this->mobile_user_agent) !== false;
    }

    /**
     * Get user fields array
     *
     * @param  string  $function  Optional, Function name
     * @return array
     */
    public function user_fields(string $function = ''): array
    {
        $fields = [
            'userID',
            'login',
            'firstName',
            'middleName',
            'lastName',
            'gender',
            'title',
            'initials',
            'email',
            'function',
            'company',
            'dateOfBirth',
            'telephoneNumber',
            'faxNumber',
            'cellPhoneNumber',
            'address',
            'zip',
            'residence',
            'country',
            'invoiceAddress',
            'invoiceZip',
            'invoiceResidence',
            'invoiceCountry',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
            'custom5',
            'locale',
            'timeZoneGroup',
            'timeZone',
            'password',
            'generatePassword',
            'forcePasswordChange',
            'notify',
            'active',
            'renewable',
            'avatar',
            'deleteAvatar',
        ];
        if ($function == 'addUser') {
            $fields = array_merge($field, [
                'role',
            ]);
        }
        if ($function == 'updateUser' || $function == 'addOrUpdateUser') {
            $fields = array_merge($field, [
                'userUID',
                'id',
                'archived',
            ]);
        }

        return $fields;
    }
    /**
     * User status
     *
     * @param  integer  $uid  User ID
     * @return string 'active', 'inactive', 'nonexisting' or 'error'
     */
    public function user_status(int $uid): string
    {
        if ($this->userExists($uid)) {
            $user = $this->getUser($uid, false);
            if (!isset($user['active'])) {
                $status = 'error';
            } elseif ($user['active']) {
                $status = 'active';
            } else {
                $status = 'inactive';
            }
        } else {
            $status = 'nonexisting';
        }

        return $status;
    }

    /**
     * Is this an user which started a course?
     *
     * @param  integer  $uid  User ID
     * @return boolean
     */
    public function user_has_started(int $uid): bool
    {
        $result = false;
        foreach ($this->getResults($uid) as $r) {
            if ($r['startDate']) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * Get all groupIDs
     *
     * @return array
     */
    public function all_groups(): array
    {
        return array_merge(array_unique(array_values($this->groups)), [$this->group_teacher]);
    }

    /**
     * Get all groups from server where user is a member of.
     *
     * @param  integer  $uid  User ID
     * @return array    Example: ['student', 'teacher']
     */
    public function get_groups(int $uid): array
    {
        $result = [];
        foreach ($this->all_groups() as $groupID) {
            if ($this->groupUserExists($groupID, $uid)) {
                $result[] = $groupID;
            }
        }

        return $result;
    }

    /**
     * Initialize array of all User IDs that exists on the aNewSpring server
     *
     * @return void    $this->user_ids, $this->user_ids_active, $this->user_ids_inactive filled
     */
    public function init_user_ids(): void
    {
        $this->user_ids          = [];
        $this->user_ids_active   = [];
        $this->user_ids_inactive = [];
        foreach ($this->getUsers() as $user) {
            $uid = (int) $user['id'];
            if ($uid) {
                $this->user_ids[] = $uid;
                if ($user['active']) {
                    $this->user_ids_active[]   = $uid;
                } else {
                    $this->user_ids_inactive[] = $uid;
                }
            }
        }
    }

    /**
     * Initialize array of per groupID all user ids on the aNewSpring server
     *
     * @return void    $this->group_users filled
     */
    public function init_group_users(): void
    {
        $this->group_users = [];
        foreach ($this->all_groups() as $groupID) {
            $this->group_users[$groupID] = $this->getGroupUsers($groupID);
        }
    }

    /**
     * Initialize array of all courseIDs that exists on the aNewSpring server
     *
     * @return void    $this->course_ids filled
     */
    public function init_course_ids(): void
    {
        $this->course_ids = [];
        foreach ($this->getTemplates() as $template) {
            foreach ($this->getCourses($template['id']) as $course) {
                if ($course['teachersAllowed'] && $course['teacherGroup'] == $this->group_teacher) {
                    $this->course_ids[] = $course['id'];
                }
            }
        }
    }

    /**
     * Initialize array of per courseID all subscribed students user ids on the aNewSpring server
     *
     * @return void    $this->subscriptions filled
     */
    public function init_subscriptions(): void
    {
        $this->subscriptions = [];
        if (!$this->course_ids) {
            $this->init_course_ids();
        }
        foreach ($this->course_ids as $courseID) {
            $this->subscriptions[$courseID] = $this->getStudentSubscriptions($courseID);
        }
    }

} // end class
