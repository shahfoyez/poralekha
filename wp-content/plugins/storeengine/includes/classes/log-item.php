<?php
namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LogItem extends AbstractEntity {
    protected string $table = 'storeengine_logs';
    protected string $primary_key = 'id';
    protected string $object_type = 'log';

    protected array $data =[
        'date'    => '',
        'module'  => '',
        'title'   => '',
        'status'  => '',
        'content' => '',
    ];

    protected array $data_format =[
        'date'    => '%s',
        'module'  => '%s',
        'title'   => '%s',
        'status'  => '%s',
        'content' => '%s',
    ];

    public function __construct( $read = 0 ) {
        parent::__construct( 0 ); 

        if ( is_array( $read ) || is_object( $read ) ) {
            $this->set_object_read( false ); 
            $this->set_props( (array) $read );
            $this->set_object_read( true ); 
        } elseif ( is_numeric( $read ) && $read > 0 ) {
            $this->set_id( $read );
            $this->read();
        }
    }

    // api response
    public function get_data(): array {
        $data = parent::get_data();
        
        return array_merge( $data,[
            'id'   => $this->get_id(),
            'date' => $this->get_formatted_date_prop( 'date', 'mysql' ) 
        ] );
    }
    
    // Getters
    public function get_date( $context = 'view' ) { 
        return $this->get_formatted_date_prop( 'date', 'mysql', false, $context ); 
    }
    public function get_module( $context = 'view' ) { return $this->get_prop('module', $context); }
    public function get_title( $context = 'view' ) { return $this->get_prop('title', $context); }
    public function get_status( string $context = 'view' ): ?string { return $this->get_prop('status', $context); }
    public function get_content( $context = 'view' ) { return $this->get_prop('content', $context); }

    // Setters
    public function set_date( $value ) { $this->set_prop('date', $value); }
    public function set_module( $value ) { $this->set_prop('module', $value); }
    public function set_title( $value ) { $this->set_prop('title', $value); }
    public function set_status( $value ) { $this->set_prop('status', $value); }
    public function set_content( $value ) { $this->set_prop('content', $value); }
}