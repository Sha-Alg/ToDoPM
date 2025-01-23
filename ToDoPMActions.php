<?php
/**
 * ToDoPMActions Class
 *
 * This class provides backend functionality for the ToDoPM web application,
 * including user management, project management, task handling, and AWS integrations.
 *
 * AWS Services Used:
 * - AWS Cognito: For user authentication and authorization.
 * - AWS S3: For file storage (e.g., user avatars).
 *
 * Database:
 * - MySQL: Stores user, project, task, and progress data.
 *
 * PHP Dependencies:
 * - AWS SDK for PHP: Handles AWS Cognito and S3 interactions.
 * - MySQLi: For database operations.
 */

// Load dependencies
include 'vendor/autoload.php';

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;

ini_set('display_errors', 1);

/**
 * Class ToDoPMActions
 *
 * This class encapsulates all actions related to user and task management
 * for the ToDoPM application, leveraging AWS services for authentication
 * and storage, and a MySQL database for data persistence.
 */
class ToDoPMActions
{
    /** @var mysqli MySQL database connection */
    private mysqli $db;

    /** @var CognitoIdentityProviderClient AWS Cognito client */
    private CognitoIdentityProviderClient $client;

    /** @var S3Client AWS S3 client */
    private S3Client $s3;

    /**
     * Constructor
     * Initializes database connection and AWS clients.
     */
    public function __construct()
    {
        ob_start();
        include 'db_connect.php'; // Load database connection

        // Initialize AWS Cognito client
        $this->client = new CognitoIdentityProviderClient([
            'region' => 'eu-north-1',
            'version' => 'latest',
            'credentials' => [
                'key' => getenv('TODOPM_ACCESS_KEY'),
                'secret' => getenv('TODOPM_SECRET_KEY'),
            ],
            'http' => [
                'verify' => false, // Disable SSL verification for development
            ],
        ]);

        // Initialize AWS S3 client
        $this->s3 = new S3Client([
            'region' => 'eu-north-1',
            'version' => 'latest',
            'credentials' => [
                'key' => getenv('TODOPM_ACCESS_KEY'),
                'secret' => getenv('TODOPM_SECRET_KEY'),
            ],
            'http' => [
                'verify' => false, // Disable SSL verification for development
            ],
        ]);

        // Assign database connection
        $this->db = $conn;
    }

    /**
     * Destructor
     * Cleans up resources (e.g., database connection, output buffer).
     */
    function __destruct()
    {
        $this->db->close();
        ob_end_flush();
    }

    /**
     * Authenticates a user by validating their email and password.
     * If the credentials are correct, it attempts to log the user in
     * through AWS Cognito and stores session information accordingly.
     * Handles scenarios where the user may not be confirmed.
     *
     * @return int|string Returns:
     *                   - 1 on successful login
     *                   - 2 if the user is not confirmed (and a confirmation code was sent)
     *                   - 3 if the password verification fails
     *                   - error message on failure
     */
    function login()
    {
        extract($_POST);
        $qry = $this->db->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where email = '" . $email . "'  ")->fetch_array();
        if (password_verify($password, $qry['password'] ?? 'true')) {
            try {
                // Authenticate user via AWS Cognito
                $response = $this->client->initiateAuth([
                    'AuthFlow' => 'USER_PASSWORD_AUTH',
                    'ClientId' => '4acegmg0quak99nj2vs7cd0v3t',
                    'AuthParameters' => [
                        'USERNAME' => $email,
                        'PASSWORD' => $password,
                    ],
                ]);

                // Store the access token in the session
                $_SESSION['_token'] = $response['AuthenticationResult']['AccessToken'];
                // Store user information in the session, excluding the password
                foreach ($qry as $key => $value) {
                    if ($key != 'password' && !is_numeric($key)) {
                        $_SESSION['login_' . $key] = $value;
                    }
                }
                return 1; // Successful login
            } catch (AwsException $e) {
                // Handle specific error for unconfirmed user
                if ($e->getAwsErrorCode() === 'UserNotConfirmedException') {
                    try {
                        // Resend confirmation code via AWS Cognito
                        $this->client->resendConfirmationCode([
                            'ClientId' => '4acegmg0quak99nj2vs7cd0v3t',
                            'Username' => $email,
                        ]);
                        $_SESSION["email"] = $email; // Store email in session for confirmation attempt
                        return 2; // User is not confirmed
                    } catch (AwsException $ee) {
                        return $ee->getAwsErrorMessage(); // Return resend confirmation error
                    }
                }
                return 'Error: ' . $e->getAwsErrorMessage(); // Return general AWS error message
            } catch (Exception $e) {
                return $e->getMessage(); // Return general error message
            }
        } else {
            return 3; // Incorrect password
        }
    }

    /**
     * Confirms a user's registration by validating the confirmation code.
     * If the code is correct and the email exists in the database,
     * the user's status is updated to confirmed in both the database
     * and AWS Cognito.
     *
     * @return int|string Returns:
     *                   - 1 on successful confirmation
     *                   - error message if user is not found or AWS error occurs
     */
    function confirm()
    {
        extract($_POST);
        $qry = $this->db->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where email = '" . $email . "'");
        if ($qry->num_rows > 0) {
            try {
                // Confirm user registration via AWS Cognito
                $this->client->confirmSignUp([
                    'ClientId' => '4acegmg0quak99nj2vs7cd0v3t',
                    'Username' => $email,
                    'ConfirmationCode' => $code,
                ]);

                // Update user's confirmed status in the database
                $this->db->query("UPDATE users SET confirmed=1 WHERE email = '$email'");
                return 1; // Successful confirmation
            } catch (AwsException $e) {
                return $e->getAwsErrorMessage(); // Return AWS error message
            }
        } else {
            return 'Unauthorized process'; // No user found with the given email
        }
    }

    /**
     * Logs out the user by destroying the session and redirecting to the login page.
     */
    function logout()
    {
        session_destroy(); // Destroy the current session
        // Unset all session variables
        foreach ($_SESSION as $key => $value) {
            unset($_SESSION[$key]);
        }
        header("location: login.php"); // Redirect to the login page
    }


    /**
     * Saves a user's information to the database and manages their email,
     * password, and avatar image. Handles both the creation of new users and
     * the update of existing users.
     *
     * The function checks if the provided email already exists in the database
     * (excluding the current user if updating). If a new avatar image is uploaded,
     * it saves it to AWS S3 and updates the avatar field in the database.
     * If the email is changed, it updates the user's email in AWS Cognito.
     *
     * It includes error handling for AWS operations and database queries.
     *
     * @return int|string Returns 1 on success, 0 on failure, or error message on error.
     */
    function save_user()
    {
        // Extracts POST data into variables for easier access
        extract($_POST);
        // Build the data string for insertion into the database.
        $data = $this->build_insert_data();

        // Check if the email already exists in the database (except for the current user if updating).
        if ($this->db->query("SELECT * FROM users where email ='$email' " . (!empty($id) ? " and id != {$id} " : ''))->num_rows > 0) {
            return 2; // Email already exists
        }

        // Initialize flags for signup and image upload status
        $signupPassed = false;
        $imagePassed = false;

        try {
            $imageName = '';
            $avatarAppend = '';

            // If updating an existing user, fetch their current avatar and password
            if (!empty($id)) {
                $user_before = $this->db->query("SELECT avatar,password,email FROM users where id =" . $id)->fetch_array();
                $avatarAppend = ", avatar = '" . $user_before['avatar'] . "'";
            }

            // Check if a new image has been uploaded; if so, save it to AWS S3
            if (isset($_FILES['img']) && $_FILES['img']['tmp_name'] != '') {
                $imageName = $this->save_image('img'); // Save uploaded image
                $imagePassed = true; // Set flag to indicate image has been processed
                $avatarAppend = ", avatar = '$imageName' "; // Use the new image in the database update
            }

            $data .= $avatarAppend; // Append avatar data to the SQL query string

            if (empty($id)) {
                // Create a new AWS Cognito user if this is a new account
                $this->create_aws_user($email, $password);
                $signupPassed = true; // Set flag indicating signup success
                $save = $this->db->query("INSERT INTO users set $data"); // Insert new user into database
            } else {
                // Handle existing user updates
                if (!empty($email) && $email != $user_before['email']) {
                    // Email has changed
                    if (empty($password)) {
                        return "To change email you must fill password fields"; // Password required for email change
                    }
                    // Update user's email in AWS Cognito
                    $this->update_email_in_aws($user_before['email'], $email, $password);
                    $signupPassed = true; // Indicate email update success
                } elseif (!empty($password) && !password_verify($password, $user_before['password'])) {
                    // Update the password if provided and verified
                    $this->update_user_password($email, $password);
                }

                // Update the user's information in the database
                $save = $this->db->query("UPDATE users set $data where id = $id");
                $imagePassed = false; // Reset flags since no image has been processed in this block
                $signupPassed = false;

                // If an old image existed, delete it from AWS S3
                if (!empty($imageName) && !empty($user_before['avatar'])) {
                    $this->delete_image($user_before['avatar']); // Delete old image
                }
            }

            return $save ? 1 : 0; // Return success or failure of database operation
        } catch (AwsException $e) {
            // Handle errors related to AWS operations
            if ($signupPassed) {
                // Delete Cognito user if signup operation was partially successful
                $this->delete_aws_user($email);
            }
            if ($imagePassed) {
                $this->delete_image($fname); // Delete uploaded image if it was saved
            }
            return json_encode(['error' => 1, 'msg' => $e->getAwsErrorMessage() ?? $e->getAwsErrorCode() ?? $e->getMessage()]);
        } catch (Exception $e) {
            // Handle general exceptions
            if ($signupPassed) {
                $this->delete_aws_user($email); // Clean up AWS user if required
            }
            if ($imagePassed) {
                $this->delete_image($fname); // Delete any uploaded image
            }
            return json_encode(['error' => 1, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Builds the SQL insert string from POST data, excluding certain fields.
     * Hashes the user's password securely before adding it to the insert data.
     *
     * @return string Returns the formatted insert data for the database query.
     */
    private function build_insert_data()
    {
        $data = ''; // Initialize the data string
        // Loop through POST data and build the insert string
        foreach ($_POST as $k => $v) {
            // Exclude specific fields and numeric keys
            if (!in_array($k, ['id', 'cpass', 'password']) && !is_numeric($k)) {
                if (empty($data)) {
                    $data .= " $k='$v' "; // First key-value pair
                } else {
                    $data .= ", $k='$v' "; // Subsequent key-value pairs
                }
            }
        }
        // Check and securely hash the password if it's present
        if (!empty($_POST['password'])) {
            $data .= ", password='" . password_hash($_POST['password'], PASSWORD_DEFAULT) . "'";
        }
        return $data; // Return the constructed string
    }


    /**
     * Save Image Method
     * Uploads an image to AWS S3.
     *
     * @param string $key Input field name for the file.
     * @return string Image file name if successful.
     */
    function save_image($key)
    {
        $name = strtotime(date('y-m-d H:i')) . '_' . $_FILES[$key]['name'];
        // Upload file to AWS S3 bucket
        $this->s3->putObject([
            'Bucket' => 'todopmbucket',
            'Key' => 'avatars/' . $name,
            'SourceFile' => $_FILES[$key]['tmp_name'],
        ]);
        return $name;
    }

    /**
     * Create AWS User Method
     * Registers a new user in AWS Cognito.
     *
     * @param string $email User's email.
     * @param string $password User's password.
     */
    private function create_aws_user($email, $password)
    {
        $this->client->signUp([
            'ClientId' => '4acegmg0quak99nj2vs7cd0v3t',
            'UserPoolId' => 'eu-north-1_Li8XBigQi',
            'Username' => $email,
            'Password' => $password,
            'UserAttributes' => [['Name' => 'email', 'Value' => $email]],
        ]);
    }

    /**
     * Updates a user's email in AWS Cognito by creating a new user with a new email,
     * then deletes the old email from the user pool.
     *
     * @param string $old_email The user's old email address.
     * @param string $new_email The user's new email address.
     * @param string $password The user's password for authentication.
     */
    private function update_email_in_aws($old_email, $new_email, $password)
    {
        // Create a new user in AWS Cognito with the new email
        $this->create_aws_user($new_email, $password);
        // Delete the old user from AWS Cognito
        $this->client->adminDeleteUser([
            'UserPoolId' => 'eu-north-1_Li8XBigQi',
            'Username' => $old_email,
        ]);
    }

    /**
     * Updates a user's password in AWS Cognito to a new permanent password.
     *
     * @param string $email The user's email address.
     * @param string $password The new password to be set for the user.
     */
    private function update_user_password($email, $password)
    {
        $this->client->adminSetUserPassword([
            'UserPoolId' => 'eu-north-1_Li8XBigQi',
            'Username' => $email,
            'Password' => $password,
            'Permanent' => true, // Set the new password as permanent
        ]);
    }

    /**
     * Delete Image Method
     * Removes an image from AWS S3.
     *
     * @param string $key Image file name.
     */
    function delete_image($key)
    {
        // Delete file from AWS S3 bucket
        $this->s3->deleteObject([
            'Bucket' => 'todopmbucket',
            'Key' => 'avatars/' . $key,
        ]);
    }

    /**
     * Delete AWS User Method
     * Deletes a user from AWS Cognito.
     *
     * @param string $email User's email.
     */
    private function delete_aws_user($email)
    {
        $this->client->adminDeleteUser([
            'UserPoolId' => 'eu-north-1_Li8XBigQi',
            'Username' => $email,
        ]);
    }

    /**
     * Deletes a user from both the database and AWS Cognito.
     * Fetches the user's email and confirmed status before attempting deletion.
     *
     * @return int|string Returns 1 on successful deletion, or error message on failure.
     */
    function delete_user()
    {
        extract($_POST); // Extract POST data into variables
        try {
            // Fetch user's email and confirmed status from the database
            $data = $this->db->query("SELECT email, confirmed FROM users where id = " . $id)->fetch_array();

            // Delete the user from AWS Cognito
            $this->delete_aws_user($data['email']);

            // Delete the user from the local database
            $delete = $this->db->query("DELETE FROM users where id = " . $id);
            if ($delete) {
                return 1; // Successfully deleted
            }
        } catch (AwsException $e) {
            return $e->getAwsErrorMessage() . $email; // Return AWS-related error message
        } catch (Throwable $e) {
            return $e->getMessage(); // Return general error message
        }
    }

    /**
     * Save Project Method
     * Creates or updates a project in MySQL.
     *
     * @return int 1 if successful.
     */
    function save_project()
    {
        extract($_POST);
        $data = "";
        foreach ($_POST as $k => $v) {
            if (!in_array($k, array('id', 'user_ids')) && !is_numeric($k)) {
                if ($k == 'description') {
                    $v = htmlentities($v);
                }
                if (empty($data)) {
                    $data .= " $k='$v' ";
                } else {
                    $data .= ", $k='$v' ";
                }
            }
        }
        if (isset($user_ids)) {
            $data .= ", user_ids='" . implode(',', $user_ids) . "' ";
        }

        if (empty($id)) {
            $save = $this->db->query("INSERT INTO project_list SET $data");
        } else {
            $save = $this->db->query("UPDATE project_list SET $data WHERE id = $id");
        }

        return $save ? 1 : 0;
    }

    /**
     * Delete Project Method
     * Deletes a project from MySQL.
     *
     * @return int 1 if successful.
     */
    function delete_project()
    {
        extract($_POST);
        $delete = $this->db->query("DELETE FROM project_list where id = $id");
        if ($delete) {
            return 1;
        }
    }

    /**
     * Save Task Method
     * Creates or updates a task in MySQL.
     *
     * @return int 1 if successful.
     */
    function save_task()
    {
        extract($_POST);
        $data = "";
        foreach ($_POST as $k => $v) {
            if (!in_array($k, array('id')) && !is_numeric($k)) {
                if ($k == 'description')
                    $v = htmlentities(str_replace("'", "&#x2019;", $v));
                if (empty($data)) {
                    $data .= " $k='$v' ";
                } else {
                    $data .= ", $k='$v' ";
                }
            }
        }
        if (empty($id)) {
            $save = $this->db->query("INSERT INTO task_list set $data");
        } else {
            $save = $this->db->query("UPDATE task_list set $data where id = $id");
        }
        if ($save) {
            return 1;
        }
    }

    /**
     * Delete Task Method
     * Deletes a task from MySQL.
     *
     * @return int 1 if successful.
     */
    function delete_task()
    {
        extract($_POST);
        $delete = $this->db->query("DELETE FROM task_list WHERE id = $id");
        return $delete ? 1 : 0;
    }

    /**
     * Save Progress Method
     * Records progress in MySQL, calculating time rendered from start and end times.
     *
     * @return int 1 if successful.
     */
    function save_progress()
    {
        extract($_POST);
        $data = "";
        foreach ($_POST as $k => $v) {
            if (!in_array($k, array('id')) && !is_numeric($k)) {
                if ($k == 'comment')
                    $v = htmlentities($v);
                if (empty($data)) {
                    $data .= " $k='$v' ";
                } else {
                    $data .= ", $k='$v' ";
                }
            }
        }
        $dur = abs(strtotime("2020-01-01 " . $end_time)) - abs(strtotime("2020-01-01 " . $start_time));
        $dur = $dur / (60 * 60);
        $data .= ", time_rendered='$dur' ";

        if (empty($id)) {
            $data .= ", user_id={$_SESSION['login_id']} ";
            $save = $this->db->query("INSERT INTO user_productivity set $data");
        } else {
            $save = $this->db->query("UPDATE user_productivity set $data where id = $id");
        }

        return $save ? 1 : 0;
    }

    /**
     * Delete Progress Method
     * Deletes a progress record from MySQL.
     *
     * @return int 1 if successful.
     */
    function delete_progress()
    {
        extract($_POST);
        $delete = $this->db->query("DELETE FROM user_productivity WHERE id = $id");
        return $delete ? 1 : 0;
    }
}

