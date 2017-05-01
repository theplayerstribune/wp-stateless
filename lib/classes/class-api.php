<?php
/**
 * API Handler
 *
 * @author alimuzzaman
 * @since 1.0.0
 */
namespace wpCloud\StatelessMedia {

  if( !class_exists( 'wpCloud\StatelessMedia\API' ) ) {

    final class API {
      public function __construct(){
        global $wpdb;

        // wordpress
        $this->namespace = 'wp-stateless/v1';
        $this->table_name = $wpdb->prefix . "stateless_job";

        // job handler service endpoint
        $this->job_handler_endpoint = 'http://api.usabilitydynamics.com/product/stateless/v1/';

        // Invoke REST API
        add_action( 'rest_api_init', array( $this, 'api_init' ) );
      }



      /**
       * Define REST API.
       *
       * // https://usabilitydynamics-sandbox-uds-io-stateless-testing.c.rabbit.ci/wp-json/wp-stateless/v1
       *
       * @author potanin@UD
       */
      public function api_init() {

        /**
         * Check current status of Stateless API.
         * Whether it's accessible or not.
         * 
         * Request parameter: none
         * 
         * Response: 
         *    ok: Whether API is up or not
         *    message: Describe what is done or error message on error.
         * 
         */
        register_rest_route( $this->namespace, '/status', array(
          'methods' => 'GET',
          'callback' => array( $this, 'status' ),
        ) );

        /**
         *** Private ***
         * Return list of job ids.
         * 
         * Request parameter: none
         * 
         * Response: 
         *    ok: Whether request succeeded or not
         *    message: Describe what is done or error message on error.
         *    jobs: array of job ids
         * 
         */
        register_rest_route( $this->namespace, '/jobs', array(
          'methods' => 'GET',
          'callback' => array( $this, 'jobs' ),
        ) );

        
        /**
         * Get job details.
         * 
         * Request parameter:
         *    id: job id
         * 
         * Response: 
         *    job.
         * 
         */
        register_rest_route( $this->namespace, '/job/(?P<id>\d+)', array(
          'methods' => 'GET',
          'callback' => array( $this, 'get_job' ),
          'id' => array(
            'validate_callback' => function($param, $request, $key) {
              return is_numeric( $param );
            }
          ),
        ) );
        

        /**
         * Synchronize attachment.
         * 
         * Request parameter:
         *    id: job id
         * 
         * Response: 
         *    ok: Whether attachment synced or not
         *    message: Describe what is done or error message on error.
         * 
         */
        register_rest_route( $this->namespace, '/process_attachment/(?P<id>\d+)', array(
          'methods' => 'GET',
          'callback' => array( $this, 'process_attachment' ),
          'id' => array(
            'validate_callback' => function($param, $request, $key) {
              return is_numeric( $param );
            }
          ),
        ) );

        /**
         *** Private ***
         * Start or stop a job.
         * 
         * Example query: /job/{id}/{step}
         *                /job/5466/start
         * 
         * Request parameter:
         *    id: job id
         *    step: start, stop, pause, resume
         * 
         * Response: 
         *    ok: Whether request succeeded or not
         *    message: Describe what is done or error message on error.
         * 
         */
        register_rest_route( $this->namespace, '/job/(?P<id>\d+)/(?P<step>\w+)', array(
          'methods' => 'GET',
          'callback' => array( $this, 'job_step' ),
          'id' => array(
            'validate_callback' => function($param, $request, $key) {
              return is_numeric( $param );
            }
          ),
        ) );

        /**
         *** Private ***
         * Create a new job and start it.
         * 
         * Example query: /job/create/
         * 
         * Request body:
         *    bulk_size: The amount of attachment to process at a time.
         * 
         * Response: 
         *    ok: Whether job started or not.
         *    message: "Job started" on success.
         *    response: Describe what is done or error message on error.
         * 
         */
        register_rest_route( $this->namespace, '/job/create/', array(
          'methods' => 'POST',
          'callback' => array( $this, 'create_job' ),
        ) );

      }

      /**
       * API Status Endpoint.
       *
       * @return array
       */
      public function status() {

        return array(
          "ok" => true,
          "message" => "API up."
        );

      }

      /**
       * Jobs list Endpoint.
       *
       * @return array of job ids
       */
      public function jobs() {
        global $wpdb;

        $where_query = "WHERE status != 'completed'";
        $sql = "SELECT id FROM {$this->table_name} $where_query";

        $jobs = $wpdb->get_col($sql);

        //$jobs_url = array();
        //foreach ($jobs as $job) {
        //  $jobs_url[] = $this->get_job_url($job['id']);
        //}

        return array(
          "ok" => true,
          "message" => "Job endpoint up.",
          "jobs" => $jobs,
        );

      }

      /**
       * Jobs Details.
       *
       * @return array
       */
      public function get_job($data) {
        global $wpdb;
        $table = $wpdb->prefix . "stateless_job";

        $where_query = "WHERE id = '{$data['id']}'";
        $sql = "SELECT * FROM {$this->table_name} $where_query";

        $job = $wpdb->get_row($sql);
        $job->url = $this->get_job_url($job->id);
        $job->url_start = $this->get_job_step_url($job->id, "start");
        $job->callback_url = $this->get_root_url("/process_image/%data_id%");
        $job->status_url = $this->get_root_url('/status');

        return $job;

      }

      /**
       * Start, stop, pause, resume a Job.
       *
       * @return array
       */
      public function job_step($data) {
        $id       = $data['id'];
        $success  = true;
        $message  = null;
        $response = null;

        switch ($data['step']) {
          case 'start':
            $message = "Job started.";
            $response = wp_remote_post( $this->job_handler_endpoint . "job/$id/start", array(
                  'body' => $this->get_job($id),
                )
            );
            break;
          case 'pause':
            $message = "Job paused.";
            $response = wp_remote_get( $this->job_handler_endpoint . "job/$id/pause");
            break;
          case 'resume':
            $message = "Job resumed.";
            $response = wp_remote_get( $this->job_handler_endpoint . "job/$id/resume");
            break;
          case 'stop':
            $message = "Job stoped.";
            $response = wp_remote_get( $this->job_handler_endpoint . "job/$id/stop");
            break;
          
          default:
            $success = false;
            $message = "Unrecognized step.";
            break;
        }

        if ( is_wp_error( $response ) ) {
          $success = false;
          $error_message = $response->get_error_message();
          $message = "Something went wrong: $error_message";
        }

        return array(
          "ok" => $success,
          "message" => $message,
          "response" => $response,
        );

      }

      /**
       * Create a Job.
       *
       * @return response of job_step() method.
       */
      public function create_job($data) {
        global $wpdb;
        $ajax       = ud_get_stateless_media()->ajax;
        $table      = $wpdb->prefix . "stateless_job";
        $bulk_size  = !empty($data['bulk_size'])?$data['bulk_size']:1;

        $job = array(
            'label'           => 'Stateless Synchronization',
            'type'            => '', // maybe remove this column 
            'status'          => 'new',
            'bulk_size'       => $bulk_size,
            'payload'         => '',
            'synced_items'    => '',
            'failed_items'    => '',
            'created_on'      => current_time( 'mysql' ), // do it in sql
            'updated_on'      => current_time( 'mysql' ), // do it in sql
            'callback_secret' => wp_generate_password( 20, true, true ),
          );

        $image_ids = $ajax->action_get_images_media_ids();
        $other_ids = $ajax->action_get_other_media_ids();
        $job['payload'] = array_merge($image_ids, $other_ids);

        $wpdb->insert( $table, $job );

        $response = $this->job_step(array('step' => 'start', 'id' => $wpdb->insert_id));

        return $response;

      } // end create_job()


      /**
       * Process attachment sync request from UD product API.
       *
       * @return response of API_F::process_attachment() method.
       *    ok: Whether attachment synced or not
       *    message: Describe what is done or error message on error.
       */
      public function process_attachment($data){
        // Both image and others.
        return API_F::process_attachment($data['id']);
      }

      /**
       * Return root API endpoint
       *
       * @param $route (Optional): If specified added with root url.
       *
       * @return API endpoint.
       */
      public function get_root_url($route = ''){
        return rest_url($this->namespace . $route);
      }

      /**
       * Endpoint for job
       *
       * @param $id: Job id.
       *
       * @return API endpoint the job id.
       */
      public function get_job_url($id){
        return $this->get_root_url('/job/' . $id);
      }

      /**
       * Endpoint for job
       *
       * @param $id: Job id.
       * @param $step: What to do with the job.
       *
       * @return API endpoint for step of the job.
       */
      public function get_job_step_url($id, $step){
        return $this->get_job_url($id) . '/step/' . $step;
      }

    }

  }

}
