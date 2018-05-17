<?php
namespace WC\SmoothGenerator\Generator;

/**
 * Product data generator.
 */
class Product extends Generator {

	/**
	 * Return a new product.
	 *
	 * @return WC_Product The unsaved product object consisting of random data.
	 */
	public function generate() {
		$faker             = Faker\Factory::create();
		$name              = $faker->words( $faker->numberBetween( 1, 5 ), true );
		$will_manage_stock = (bool) rand( 0, 1 );
		$is_virtual        = (bool) rand( 0, 1 );
		$gallery           = $this->get_gallery_image_ids();
		$price             = $this->randomFloat( 2, 1, 1000 );
		$is_on_sale        = (bool) rand( 0, 1 );
		$sale_price        = $is_on_sale ? $this->randomFloat( 2, 0, $price ): '';
		$product           = new WC_Product();

		$product->set_props( array(
			'name'               => $name,
			'featured'           => (bool) rand( 0, 1 ),
			'catalog_visibility' => 'visible',
			'description'        => $faker->paragraphs( $faker->numberBetween( 1, 5 ), true ),
			'short_description'  => $faker->text(),
			'sku'                => sanitize_title( $name ) . '-' . $faker->ean8,
			'regular_price'      => $price,
			'sale_price'         => $sale_price,
			'date_on_sale_from'  => '',
			'date_on_sale_to'    => $faker->dateTime( date( 'c', strtotime( '+1 month' ) ) ),
			'total_sales'        => $faker->numberBetween( 0, 10000 ),
			'tax_status'         => 'taxable',
			'tax_class'          => '',
			'manage_stock'       => $will_manage_stock,
			'stock_quantity'     => $will_manage_stock ? $faker->numberBetween( -100, 100 ) : null,
			'stock_status'       => 'instock',
			'backorders'         => $faker->randomElement( array( 'yes', 'no', 'notify' ) ),
			'sold_individually'  => (bool) rand( 0, 1 ),
			'weight'             => $is_virtual ? '' : $faker->numberBetween( 1, 200 ),
			'length'             => $is_virtual ? '' : $faker->numberBetween( 1, 200 ),
			'width'              => $is_virtual ? '' : $faker->numberBetween( 1, 200 ),
			'height'             => $is_virtual ? '' : $faker->numberBetween( 1, 200 ),
			'upsell_ids'         => $this->get_existing_product_ids(),
			'cross_sell_ids'     => $this->get_existing_product_ids(),
			'parent_id'          => 0,
			'reviews_allowed'    => (bool) rand( 0, 1 ),
			'purchase_note'      => (bool) rand( 0, 1 ) ? $faker->text() : '',
			'menu_order'         => $faker->numberBetween( 0, 10000 ),
			'virtual'            => $is_virtual,
			'downloadable'       => false,
			'category_ids'       => $this->generate_term_ids( rand( 1, 10 ), 'product_cat' ),
			'tag_ids'            => $this->generate_term_ids( rand( 1, 10 ), 'product_tag' ),
			'shipping_class_id'  => 0,
			'image_id'           => array_shift( $gallery ),
			'gallery_image_ids'  => $gallery,
		) );

		return $product->save();
	}

	/**
	 * Generate an image gallery.
	 *
	 * @return array
	 */
	protected function get_gallery_image_ids() {
		$gallery = array();

		for ( $i = 0; $i < rand( 1, 6 ); $i ++ ) {
			$gallery[] = $this->generate_image();
		}

		return $gallery;
	}

	/**
	 * Get some random existing product IDs.
	 *
	 * @param int $limit Number of term IDs to get.
	 * @return array
	 */
	protected function get_existing_product_ids( $limit = 5 ) {
		$post_ids = get_posts( array(
			'numberposts' => $limit * 2,
			'orderby'     => 'date',
			'post_type'   => 'product',
			'fields'      => 'ids',
		) );

		if ( ! $post_ids ) {
			return array();
		}

		shuffle( $post_ids );

		return array_slice( $post_ids, 0, max( count( $post_ids ), $limit ) );
	}
}
