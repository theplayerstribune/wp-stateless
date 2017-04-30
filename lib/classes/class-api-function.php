<?php
/**
 * API Functions
 *
 * @author alimuzzaman
 * @since 1.0.0
 */
namespace wpCloud\StatelessMedia {

  if( !class_exists( 'wpCloud\StatelessMedia\API_F' ) ) {

    final class API_F {

      static $gs_client = ud_get_stateless_media()->get_client();

      /**
       * Regenerate image sizes.
       */
      public static function process_attachment($id) {
        @error_reporting( 0 );
        $type = null;
        $generated_from_gstorage = false;
        $upload_dir = wp_upload_dir();
        $attachment = get_post( $id );
        $log = array(
            'ok' => true,
            'message' => '',
          );

        // Checking whether the post is attachment.
        if ( ! $attachment || 'attachment' != $attachment->post_type ){
          $log['ok'] = false;
          $log['message'] = sprintf( __( 'Failed resize: %s is an invalid attachment ID.', ud_get_stateless_media()->domain ), esc_html( $id ) );
          return $log;
        }

        // Getting the attachment type.
        if('image/' != substr( $attachment->post_mime_type, 0, 6 )){
          $type = 'images';
        }
        else{
          $type = 'other';
        }

        $fullsizepath = get_attached_file( $attachment->ID );
        // Extracting attachment name from full attachment file path.
        $attachment_name = apply_filters( 'wp_stateless_file_name', str_replace( trailingslashit( $upload_dir[ 'basedir' ] ), '', $fullsizepath ), $id);
        
        // If no file found
        if ( false === $fullsizepath || ! file_exists( $fullsizepath ) ) {
          $generated_from_gstorage = true;

          // Try get it and save
          $result_code = ud_get_stateless_media()->get_client()->get_media( $attachment_name, true, $fullsizepath );

          // File not exists in google storage
          if ( $result_code !== 200 ) {
            $this->store_failed_attachment( $attachment->ID, $type );
            $log['ok'] = false;
            $log['message'] = sprintf(__('Both local and remote files are missing. Unable to resize. (%s)', ud_get_stateless_media()->domain), $attachment->guid);
            return $log;
          }
        }


        if( $type == 'images' || (!$generated_from_gstorage && !$gs_client->media_exists( $attachment_name )) ){

          @set_time_limit( -1 );
          // Images will be uploaded to gstorage via hooks of wp_generate_attachment_metadata
          $metadata = wp_generate_attachment_metadata( $file->ID, $fullsizepath );

          if ( is_wp_error( $metadata ) ) {
            $this->store_failed_attachment( $file->ID, $type );
            $log['ok'] = false;
            $log['message'] = $metadata->get_error_message();
          }
          if ( empty( $metadata ) ) {
            $this->store_failed_attachment( $file->ID, $type );
            $log['ok'] = false;
            $log['message'] = __('Unknown failure reason.', ud_get_stateless_media()->domain);
          }

          wp_update_attachment_metadata( $file->ID, $metadata );

        }

        $this->store_current_progress( 'other', $id );
        $this->maybe_fix_failed_attachment( 'other', $file->ID );

        if($type == 'images'){
          $log['message'] = sprintf( __( '%1$s (ID %2$s) was successfully resized in %3$s seconds.', ud_get_stateless_media()->domain ), esc_html( get_the_title( $image->ID ) ), $image->ID, timer_stop() );
        }
        else if($type == 'other'){
          $log['message'] = sprintf( __( '%1$s (ID %2$s) was successfully synchronised in %3$s seconds.', ud_get_stateless_media()->domain ), esc_html( get_the_title( $file->ID ) ), $file->ID, timer_stop() );
        }
        return $log;
      }

      //*** Need to use monolog.

      /**
       * @param $attachment_id
       * @param $mode
       */
      private static function store_failed_attachment( $attachment_id, $mode ) {
        if ( $mode !== 'other' ) {
          $mode = 'images';
        }

        $fails = get_option( 'wp_stateless_failed_' . $mode );
        if ( !empty( $fails ) && is_array( $fails ) ) {
          if ( !in_array( $attachment_id, $fails ) ) {
            $fails[] = $attachment_id;
          }
        } else {
          $fails = array( $attachment_id );
        }

        update_option( 'wp_stateless_failed_' . $mode, $fails );
      }

      /**
       * @param $mode
       * @param $attachment_id
       */
      private static function maybe_fix_failed_attachment( $mode, $attachment_id ) {
        $fails = get_option( 'wp_stateless_failed_' . $mode );

        if ( !empty( $fails ) && is_array( $fails ) ) {
          if ( in_array( $attachment_id, $fails ) ) {
            foreach (array_keys($fails, $attachment_id) as $key) {
              unset($fails[$key]);
            }
          }
        }

        update_option( 'wp_stateless_failed_' . $mode, $fails );
      }

      /**
       * @param $mode
       * @param $id
       */
      private static function store_current_progress( $mode, $id ) {
        if ( $mode !== 'other' ) {
          $mode = 'images';
        }

        $first_processed = get_option( 'wp_stateless_' . $mode . '_first_processed' );
        if ( ! $first_processed ) {
          update_option( 'wp_stateless_' . $mode . '_first_processed', $id );
        }
        $last_processed = get_option( 'wp_stateless_' . $mode . '_last_processed' );
        if ( ! $last_processed || $id < (int) $last_processed ) {
          update_option( 'wp_stateless_' . $mode . '_last_processed', $id );
        }
      }


    }
  }
}