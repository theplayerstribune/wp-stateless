<?php

namespace wpCloud\StatelessMedia {

  use WP_Error;
  use Exception;

  if( !class_exists( 'wpCloud\StatelessMedia\Mock_Client' ) ) {

    final class Mock_Client {

      private static $instance;
      private $bucket;

      private $object_cache = [];

      protected function __construct( $args ) {
        global $current_blog;

        $this->bucket = $args[ 'bucket' ];
        $this->key_json = json_decode($args['key_json'], 1);
      }

      public function list_objects( $bucket, $options = array() ) {
        return [];
      }

      public function list_all_objects( $bucket, $options = array() ) {
        return [];
      }

      public function add_media( $args = array() ) {
        extract( $args = wp_parse_args( $args, array(
            'name' => false,
            'absolutePath' => false,
            'mimeType' => 'image/jpeg',
            'metadata' => array(),
        ) ) );

        if( empty( $name ) ) {
            $name = basename( $args['name'] );
        }
        $name = apply_filters( 'wp_stateless_file_name', $name );
        $this->object_cache[ $name ] = $args;

        return $this->media_exists( $name );
      }

      public function media_exists( $path ) {
        $object = $this->object_cache[ $path ];

        $generation      = sprintf( '%016d', rand(1000000000000000, 9999999999999999) );
        $bucket          = ud_get_stateless_media()->get( 'sm.bucket' );
        $cache_control   = array_key_exists( 'cacheControl', $object ) ? $object['cacheControl'] : null;
        $content_disp    = array_key_exists( 'contentDisposition', $object ) ? $object['contentDisposition'] : null;
        $urlencoded_path = urlencode( $path );
        $timestamp       = gmdate('Y-m-d\TH:i:s\Z');

        $media = [
            'id'                 => "${bucket}/${path}/${generation}",
            'name'               => $path,
            'mediaLink'          => "https://www.googleapis.com/download/storage/v1/b/${bucket}/o/${urlencoded_path}?generation=${generation}&alt=media",
            'selfLink'           => "https://www.googleapis.com/storage/v1/b/${bucket}/o/${urlencoded_path}",
            'storageClass'       => 'MULTI_REGIONAL',
            'bucket'             => $bucket,
            'cacheControl'       => $cache_control,
            'contentDisposition' => $content_disp,
            'contentType'        => $object['mimeType'],
            'generation'         => $generation,
            'metadata'           => $object['metadata'],
            'componentCount'     => null,
            'contentEncoding'    => null,
            'contentLanguage'    => null,
            'crc32c'             => '12345',
            'etag'               => '12345',
            'kind'               => '12345',
            'md5Hash'            => '12345',
            'metageneration'     => '1',
            'size'               => '12345',
            'timeCreated'        => $timestamp,
            'timeDeleted'        => null,
            'updated'            => $timestamp,
        ];
        return $media;
      }

      public function get_media( $path, $save = false, $save_path = false ) {
        return $this->media_exists( $path );
      }

      public function remove_media( $name ) {
        return true;
      }

      public function is_connected() {
        return true;
      }

      public static function get_instance( $args ) {
        if( null === self::$instance ) {
          try {
            if( empty( $args[ 'bucket' ] ) ) {
              throw new Exception( __( '<b>Bucket</b> parameter must be provided.' ) );
            }

            $json = "{}";

            if ( !empty( $args[ 'key_json' ] ) ) {
              $json = json_decode($args['key_json']);
            }

            if( !$json || !property_exists($json, 'private_key') ){
              throw new Exception( __( '<b>Service Account JSON</b> is invalid.' ) );
            }

            self::$instance = new self( $args );
          } catch( Exception $e ) {
            return new WP_Error( 'sm_error', $e->getMessage() );
          }
        }
        return self::$instance;
      }

    }

  }

}
