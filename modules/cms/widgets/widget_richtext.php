<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Nails_CMS_Widget_richtext
{
	static function details()
	{
		$_d	= new stdClass();
		
		$_d->name	= 'Rich Text';
		$_d->slug	= 'Widget_richtext';
		
		return $_d;
	}
	
	// --------------------------------------------------------------------------
	
	
	private $_body;
	private $_key;
	
	// --------------------------------------------------------------------------
	
	public function __construct()
	{
		$this->_body	= '';
		$this->_key		= 'richtext';
	}
	
	
	// --------------------------------------------------------------------------
	
	
	public function setup( $data )
	{
		if ( isset( $data->body ) ) :
		
			$this->_body = $data->body;
		
		endif;
		
		// --------------------------------------------------------------------------
		
		if ( isset( $data->key ) && ! is_null( $data->key ) ) :
		
			$this->_key = $data->key;
		
		endif;
	}
	
	// --------------------------------------------------------------------------
	
	public function render()
	{
		return $this->_body;
	}
	
	// --------------------------------------------------------------------------
	
	public function get_editor_draggable_html()
	{
		$_details = self::details();
		
		//	Return editor HTML
		$_out  = '<li class="widget ' . $_details->slug . '" data-template="' . $_details->slug . '">';
		$_out .= $_details->name;
		$_out .= '</li>';
		
		// --------------------------------------------------------------------------
		
		return $_out;
	}
	
	// --------------------------------------------------------------------------
	
	public function get_editor_html()
	{
		$_details = self::details();
		
		//	Return editor HTML
		$_out  = '<li class="holder">';
		
		$_out .= '<h2 class="handle">';
		$_out .= $_details->name;
		$_out .= '<a href="#" class="close">Close</a>';
		$_out .= '</h2>';
		$_out .= '<div class="editor-content">';
		$_out .= '<p>';
		$_out .= form_textarea( $this->_key . 'body', set_value( $this->_key . '[body]', $this->_body ), 'class="ckeditor"' );
		$_out .= '</p>';
		$_out .= '</div>';

		$_out .= '</li>';
		
		// --------------------------------------------------------------------------
		
		//	JS
		$_out .=  '<script type="text/javascript">';
		$_out .= 'CKEDITOR.replaceAll( function( textarea, config ) {';
		$_out .= 'if ( typeof(CKEDITOR.instances[textarea.name]) == \'undefined\' && $(textarea).hasClass( \'ckeditor\' ) )';
		$_out .= '{';
		$_out .= '	return true;';
		$_out .= '} else {';
		$_out .= '	return false;';
		$_out .= '}';
		$_out .= '})';
		$_out .= '</script>';
		
		// --------------------------------------------------------------------------
		
		return $_out;
	}
}