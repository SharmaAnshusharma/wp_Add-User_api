<?php
	/**
 *
 * @wordpress-plugin
 * Plugin Name:       Add User
 * Description:       This Plugin contain all rest api.
 * Version:           1.0
 * Author:            Anshu Sharma
 */

ob_start();

define('SITE_URL',site_url());
require_once( ABSPATH . 'wp-admin/includes/image.php' );
require_once( ABSPATH . 'wp-admin/includes/file.php' );
require_once( ABSPATH . 'wp-admin/includes/media.php' );

require_once(ABSPATH.'wp-admin/includes/user.php');
use Firebase\JWT\JWT;
class CRC_REST_API extends WP_REST_Controller 
{
	private $api_namespace;
	private $api_version;
	private $required_capability;


	public function __construct()
	{
		$this->api_namespace = 'api/v';
		$this->api_version = '1';
		$this->required_capability='read';
		$this->init();

		$headers = getallheaders(); 
		if (isset($headers['Authorization']))
        { 
        	if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches))
            { 
            	$this->user_token =  $matches[1]; 
        	} 
        }
	}
 
	private function successResponse($message="",$data=array())
	{
		$response = array();
		$response['status'] = 'success';
		$response['message'] = $message;
		$response['data'] = $data;
		return new WP_REST_Response($response,200);	
	}
	private function errorResponse($message="", $type='ERROR',$statusCode=200)
	{
		$response = array();
		$response['status'] = 'error';
		$response['message'] = $message;
		return new WP_REST_Response($response,$statusCode);
	}

	/*this code is for registering your routes for eg-: you have created a routes named as register_User*/
	public function register_routes()
	{
		$namespace = $this->api_namespace . $this->api_version;

			    $publicItems = array('register_User','getUserById','deleteUserById','updateUserById','changePassword');

		foreach($publicItems as $Items)
		{
			register_rest_route($namespace,'/'.$Items,array(
				array(
				'methods'=>'POST',
				'callback'=>array($this, $Items)
					),
				)
			);
		}
	}

	public function init()
	{
		add_action('rest_api_init',array($this,'register_routes'));
		add_action( 'rest_api_init', function()
        {
			remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
			add_filter( 'rest_pre_serve_request', function( $value )
            {
				header( 'Access-Control-Allow-Origin: *' );
				header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE' );
				header( 'Access-Control-Allow-Credentials: true' );
				return $value;
			});
		}, 15 );
	}

	public function isUserExists($user)
    	{
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->users WHERE ID = %d", $user));
        if ($count == 1)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function getUserIdByToken($token)
    {
        $decoded_array = array();
        $user_id = 0;
        if ($token)
        {
            try
            {
                $decoded = JWT::decode($token, JWT_AUTH_SECRET_KEY, array('HS256'));
                $decoded_array = (array) $decoded;
            }
            catch(\Firebase\JWT\ExpiredException $e)
            {

                return false;
            }
        } 
        if (!empty($decoded_array['data']->user->id)>0)
        {
            $user_id = $decoded_array['data']->user->id;
        }
        if ($this->isUserExists($user_id))
        {
            return $user_id;
        }
        else
        {
            return false;
        }
    }
    private function isValidToken()
    {
    	$this->user_id  = $this->getUserIdByToken($this->user_token);
    }
    

	public function register_User($request)
	{
		global $wpdb;
		$param = $request->get_params();
		$username = $param['username'];
		$email = $param['email'];
		$password = $param['password'];
		$mobile = $param['mobile'];
		$designation = $param['designation'];
		$role = !empty($param['role'])?$param['role']:'subscriber';
		$file = $_FILES['myfile'];
		$_FILES = array('myfile'=>$file);
		$attachment_id = media_handle_upload("myfile",0);


		if(empty($username))
	    {
	      	return $this->successResponse('Username Can not be empty');
	    }
	    else if(empty($email))
	    {
	      	return $this->successResponse('Email Can not be empty');
	    }
	    else if(empty($password))
		{
			return $this->successResponse('Password Can not be empty');
		}	    
	    else if(empty($mobile))
	    {
	    	return $this->successResponse('Mobile Number Can not be empty');
	    }
	    else if(empty($designation))
	    {
	    	return $this->successResponse('Designation Can not be empty');
	    }
    	if(email_exists($email))
		{
			return $this->successResponse('Email already exists');
		}
		
		else
		{
			$user_id = wp_create_user($username, $password, $email);
			$user = new WP_User($user_id);
			$user->set_role($role);
			update_user_meta($user_id, 'Mobile',$mobile);
			update_user_meta($user_id, 'Designation',$designation);
			update_user_meta($user_id, 'Profile',$attachment_id);
			if(empty($user_id))
			{
				return $this->successResponse('Problem in creating user');
			}
			else
			{
				return $this->successResponse('User Created Successfully!');
			}
		}
	}
	function jwt_auth($data, $user)
    {
        //print_r($data);
        unset($data['user_nicename']);
        unset($data['user_display_name']); 
        $site_url = site_url();
            $result['token'] =  $data['token'];
            return $this->successResponse('User Logged in successfully',$result);
    
    }

    public	function getUserById($request)
    {
        global $wpdb;
		$param = $request->get_params();
        $this->isValidToken();
        $user_id = !empty($this->user_id)?$this->user_id:$param['ID'];
         	$data = $wpdb->get_results("SELECT * FROM `wp_users` WHERE `ID` = '$user_id' ");
	       	if(count($data) > 0)
	       	{


	       		$data['mobile'] = get_user_meta($user_id,'Mobile',true);
	       		$data['designation'] = get_user_meta($user_id,'Designation',true);

     			return $this->successResponse('User Details',$data);
     		}
     		else
     		{
     			return $this->successResponse('No Data Found');
     		}
		}

	public	function deleteUserById($request)
    {
    	global $wpdb;
    	$param = $request->get_params();
        $this->isValidToken();
        $user_id = !empty($this->user_id)?$this->user_id:$param['ID'];
		
    	$query = wp_delete_user($user_id,$reassign = null);
	      if($query == 1)
	      {
	        return $this->successResponse('Data Deleted successfully!'); 
	      }
	      else
	      {
	        return $this->successResponse('No Data Found!');  
	      }

	}
	function get_profile($id)
	{
		$userData = get_user_meta($id);
		$ressult['Mobile'] =$userData['Mobile'];
		$result['Designation'] = $userData['Designation'];
		return $result;

	}

	public function updateUserById($request)
	{
		global $wpdb;
		$param = $request->get_params();
        $this->isValidToken();
        $user_id = !empty($this->user_id)?$this->user_id:$param['ID'];
        $mobile = $param['mobile'];
		$designation = $param['designation'];
		if(empty($mobile))
	    {
	    	return $this->successResponse('Mobile Number Can not be empty');
	    }
	    else if(empty($designation))
	    {
	    	return $this->successResponse('Designation Can not be empty');
	    }
		update_user_meta($user_id,'Mobile',$mobile);
		update_user_meta($user_id,'Designation',$designation);
		$dataVal = $this->get_profile($user_id);
		return $this->successResponse('Data Updated Successfully',$dataVal);
	}

	public function changePassword($request)
	{
		$param = $request->get_params();
        $this->isValidToken();
        $user_id = !empty($this->user_id)?$this->user_id:$param['ID'];
		$userData =  get_userdata($user_id);
		$old_password = $param['old_password'];
		$new_password = $param['new_password'];

		if(empty($old_password))
		{
			return $this->successResponse('Old Password is Required');
		} 
		else
		{
			$old_password_exists = wp_check_password($old_password,$userData->user_pass,$user_id);
			
			if(!empty($old_password_exists))
			{
					$ckeckPass =  wp_set_password($new_password, $user_id );
					if(empty($ckeckPass))
					{
						return $this->successResponse('Password Updated Successfully!');
					}
					else
					{
						return $this->successResponse('Password Not Updated !');	
					}
			}
			else
			{
				return $this->successResponse('Password does not exists!');
			}
		}
     
    }
}
$serverApi = new CRC_REST_API();
$serverApi->init();
add_filter('jwt_auth_token_before_dispatch',array($serverApi,'jwt_auth'),10,2);

?>