<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Name:		blog_tag_model
 *
 * Description:	This model handles all things tag related
 * 
 **/

class Blog_tag_model extends NAILS_Model
{
	public function get_all( $include_count = FALSE )
	{
		$this->db->select( 't.id,t.slug,t.label' );

		if ( $include_count ) :

			$this->db->select( '(SELECT COUNT(DISTINCT post_id) FROM blog_post_tag WHERE tag_id = t.id) post_count' );

		endif;

		$_tags = $this->db->get( 'blog_tag t' )->result();

		foreach ( $_tags AS $tag ) :

			$this->_format_tag( $tag );

		endforeach;

		return $_tags;
	}


	// --------------------------------------------------------------------------


	public function get_by_id( $id, $include_count = FALSE  )
	{
		$this->db->where( 't.id', $id );
		$_tag = $this->get_all( $include_count );

		if ( ! $_tag )
			return FALSE;

		return $_tag[0];
	}


	// --------------------------------------------------------------------------


	public function get_by_slug( $slug, $include_count = FALSE  )
	{
		$this->db->where( 't.slug', $slug );
		$_tag = $this->get_all( $include_count );

		if ( ! $_tag )
			return FALSE;

		return $_tag[0];
	}


	// --------------------------------------------------------------------------


	public function create( $label )
	{
		$_slug = $this->_generate_slug( $label );
		$this->db->set( 'slug', $_slug );
		$this->db->set( 'label', $label );
		$this->db->set( 'created', 'NOW()', FALSE );
		$this->db->set( 'modified', 'NOW()', FALSE );

		if ( $this->user->is_logged_in() ) :

			$this->db->set( 'created_by', active_user( 'id' ) );
			$this->db->set( 'modified_by', active_user( 'id' ) );

		endif;

		$this->db->insert( 'blog_tag' );

		return (bool) $this->db->affected_rows();
	}

	// --------------------------------------------------------------------------


	public function update( $id_slug, $label )
	{
		$_slug = $this->_generate_slug( $label );
		$this->db->set( 'slug', $_slug );
		$this->db->set( 'label', $_slug );
		$this->db->set( 'modified', 'NOW()', FALSE );

		if ( $this->user->is_logged_in() ) :

			$this->db->set( 'modified_by', active_user( 'id' ) );

		endif;

		if ( is_numeric( $id_slug ) ) :

			$this->db->where( 'id', $id_slug );

		else :

			$this->db->where( 'slug', $id_slug );

		endif;

		$this->db->update( 'blog_tag' );

		return (bool) $this->db->affected_rows();
	}


	// --------------------------------------------------------------------------


	public function destroy( $id_slug )
	{
		if ( ! $id_slug ) :

			return FALSE;

		endif;

		// --------------------------------------------------------------------------

		if ( is_numeric( $id_slug ) ) :

			$this->db->where( 'id', $id_slug );

		else :

			$this->db->where( 'slug', $id_slug );

		endif;

		$this->db->delete( 'blog_tag' );

		return (bool) $this->db->affected_rows();
	}


	// --------------------------------------------------------------------------


	public function delete( $id_slug )
	{
		return $this->destroy( $id_slug );
	}


	// --------------------------------------------------------------------------


	private function _format_tag( &$tag )
	{
		$tag->id	= (int) $tag->id;

		if ( isset( $tag->post_count ) ) :

			$tag->post_count	= (int) $tag->post_count;

		endif;
	}


	// --------------------------------------------------------------------------

	private function _generate_slug( $label )
	{
		$_counter = 0;
		
		do
		{
			$_slug = url_title( $label, 'dash', TRUE );

			if ( $_counter ) :

				$_slug_test = $_slug . '-' . $_counter;

			else :

				$_slug_test = $_slug;

			endif;

			$this->db->where( 'slug', $_slug_test );
			$_counter++;

		} while( $this->db->count_all_results( 'blog_tag' ) );

		return $_slug_test;
	}
}

/* End of file blog_tag_model.php */
/* Location: ./application/models/blog_tag_model.php */