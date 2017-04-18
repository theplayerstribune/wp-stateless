<?php
/**
 * API Handler
 *
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

        // job handler service
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

        register_rest_route( $this->namespace, '/status', array(
          'methods' => 'GET',
          'callback' => array( $this, 'status' ),
        ) );

        register_rest_route( $this->namespace, '/jobs', array(
          'methods' => 'GET',
          'callback' => array( $this, 'jobs' ),
        ) );

        register_rest_route( $this->namespace, '/job/(?P<id>\d+)', array(
          'methods' => 'GET',
          'callback' => array( $this, 'get_job' ),
          'id' => array(
            'validate_callback' => function($param, $request, $key) {
              return is_numeric( $param );
            }
          ),
        ) );

        register_rest_route( $this->namespace, '/process_attachment/(?P<id>\d+)', array(
          'methods' => 'GET',
          'callback' => array( $this, 'get_job' ),
          'id' => array(
            'validate_callback' => function($param, $request, $key) {
              return is_numeric( $param );
            }
          ),
        ) );

        register_rest_route( $this->namespace, '/job/(?P<id>\d+)/step/(?P<step>\d+)', array(
          'methods' => 'GET',
          'callback' => array( $this, 'start_job' ),
          'id' => array(
            'validate_callback' => function($param, $request, $key) {
              return is_numeric( $param );
            }
          ),
          'step' => array(
            'validate_callback' => function($param, $request, $key) {
              return is_numeric( $param );
            }
          ),
        ) );

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
       * Jobs Endpoint.
       *
       * @return array
       */
      public function jobs() {
        global $wpdb;

        $where_query = "WHERE status != 'completed'";
        $sql = "SELECT id FROM {$this->table_name} $where_query";

        $jobs = $wpdb->get_col($sql);

        $jobs_url = array();
        foreach ($jobs as $job) {
          $jobs_url[] = $this->get_job_url($job['id']);
          # code...
        }

        return array(
          "ok" => true,
          "message" => "Job endpoint up.",
          "jobs" => $jobs_url,
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

        return array(
          "ok" => true,
          "message" => "Job Details.",
          "response" => $job,
        );

      }

      /**
       * Start a Job.
       *
       * @return array
       */
      public function start_job($data) {
        $success = true;
        $message = "Job started.";
        $response = wp_remote_post( $this->job_handler_endpoint . 'job/start', array(
              'body' => $this->get_job($data['id']),
            )
        );

        if ( is_wp_error( $response ) ) {
          $success = false;
          $error_message = $response->get_error_message();
          $message = "Something went wrong: $error_message";
        }

        return array(
          "ok" => $success,
          "message" => $message,
          "progress" => $response,
        );

      }

      /**
       * Start a Job.
       *
       * @return array
       */
      public function create_job($data) {
        global $wpdb;
        $table      = $wpdb->prefix . "stateless_job";
        $bulk_size  = !empty($data['bulk_size'])?$data['bulk_size']:1;
        $action     = !empty($data['action'])?$data['action']:'regenerate_images';

        $job = array(
            'label'           => 'Stateless Synchronization',
            'type'            => $action,
            'status'          => 'new',
            'bulk_size'       => $bulk_size,
            'payload'         => '',
            'synced_items'    => '',
            'failed_items'    => '',
            'created_on'      => current_time( 'mysql' ),
            'updated_on'      => current_time( 'mysql' ),
            'callback_secret' => wp_generate_password( 12, true, true ),
          );
        $ajax = ud_get_stateless_media()->ajax;

        if($data['action'] == 'regenerate_images'){
          $job['payload'] = $ajax->action_get_images_media_ids();
        }
        else if($data['action'] == 'sync_non_images'){
          $job['payload'] = $ajax->action_get_other_media_ids();
        }

        $wpdb->insert( $table, $job );

        $job = array('id' => $wpdb->insert_id) + $job;

        return array(
          "ok" => true,
          "message" => "Job started.",
          "job" => $job,
        );

      } // end create_job()


      public function get_root_url($route = ''){
        return rest_url($this->namespace . $route);
      }

      public function get_job_url($id){
        return rest_url($this->namespace . '/job/' . $id);
      }

      public function get_job_step_url($id, $step){
        return $this->get_job_url($id) . "/step/" . $step;
      }

    }

  }

}
