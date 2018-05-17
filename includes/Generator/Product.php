<?php
namespace WC\SmoothGenerator\Generator;

/**
 * Product data generator.
 */
class Product extends Generator {

	/**
	 * Return a new array of data.
	 *
	 * @return array
	 */
	public function generate() {
		$faker             = Faker\Factory::create();
		$name              = $faker->words( $faker->numberBetween( 1, 5 ), true );
		$will_manage_stock = (bool) rand( 0, 1 );
		$is_virtual        = (bool) rand( 0, 1 );

		return array(
			'name'               => $name,
			'featured'           => (bool) rand( 0, 1 ),
			'catalog_visibility' => 'visible',
			'description'        => $faker->paragraphs( $faker->numberBetween( 1, 5 ), true ),
			'short_description'  => $faker->text(),
			'sku'                => sanitize_title( $name ) . '-' . $faker->ean8,
			'price'              => rand( 1, 10000 ) / ( rand( 1, 4 ) * 10 ),
			'regular_price'      => '',
			'sale_price'         => '',
			'date_on_sale_from'  => '',
			'date_on_sale_to'    => $faker->dateTime( date( 'c', strtotime( '+1 month' ) ) ),
			'total_sales'        => $faker->numberBetween( 0, 10000 ),
			'tax_status'         => 'taxable',
			'tax_class'          => '',
			'manage_stock'       => $will_manage_stock,
			'stock_quantity'     => $will_manage_stock ? $faker->numberBetween( -100, 100 ) : null,
			'stock_status'       => 'instock',
			'backorders'         => 'no',
			'sold_individually'  => (bool) rand( 0, 1 ),
			'weight'             => $is_virtual ? '' : $faker->numberBetween( 1, 200 ),
			'length'             => $is_virtual ? '' : $faker->numberBetween( 1, 200 ),
			'width'              => $is_virtual ? '' : $faker->numberBetween( 1, 200 ),
			'height'             => $is_virtual ? '' : $faker->numberBetween( 1, 200 ),
			'upsell_ids'         => $this->get_random_existing_ids(),
			'cross_sell_ids'     => $this->get_random_existing_ids(),
			'parent_id'          => 0,
			'reviews_allowed'    => (bool) rand( 0, 1 ),
			'purchase_note'      => (bool) rand( 0, 1 ) ? $faker->text() : '',
			'attributes'         => array(),
			'default_attributes' => array(),
			'menu_order'         => $faker->numberBetween( 0, 10000 ),
			'virtual'            => $is_virtual,
			'downloadable'       => false,
			'category_ids'       => array(),
			'tag_ids'            => array(),
			'shipping_class_id'  => 0,
			'downloads'          => array(),
			'image_id'           => '',
			'gallery_image_ids'  => array(),
			'download_limit'     => -1,
			'download_expiry'    => -1,
		);
	}

	/**
	 * Get some random existing product IDs.
	 *
	 * @return array
	 */
	protected function get_random_existing_ids() {
		return array();
	}

}
