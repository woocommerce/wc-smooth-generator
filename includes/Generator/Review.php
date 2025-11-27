<?php
/**
 * Product review generator class
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

/**
 * Review data generator.
 */
class Review extends Generator {

	/**
	 * Fake review seed content.
	 *
	 * @var array
	 */
	protected static $fake_reviews = array(
		'Love this! Well made, sturdy, and very comfortable. I love it!Very pretty',
		'love it, a great upgrade from the original. I\'ve had mine for a couple of years',
		'This pillow saved my back. I love the look and feel of this pillow.',
		'Missing information on how to use it, but it is a great product for the price! I',
		'Very nice set. Good quality. We have had the set for two months now and have not been',
		'I WANTED DIFFERENT FLAVORS BUT THEY ARE NOT.',
		'They are the perfect touch for me and the only thing I wish they had a little more space.',
		'These done fit well and look great. I love the smoothness of the edges and the extra',
		'Great big numbers & easy to read, the only thing I didn\'t like is the size of the',
		'My son loves this comforter and it is very well made. We also have a baby',
		'As advertised. 5th one I\'ve had. The only problem is that it\'s not really a',
		'Very handy for one of my kids and the tools are included in the package. I have one in',
		'Did someone say, "Oriental for $60"? It is a great product for the',
		'These are so flimsy! They are not the quality you would expect from a piece of furniture.',
		'Makes may tea with out stirring. The only problem is that it\'s kind of hard to put',
		'Absolutely adorable! And excellent price. We have had the wooden ones for a few months now and they',
		'Love this! Perfect size for an entire family!Very good quality.',
		'These look beautiful and so nice. The only problem is that it\'s not really a mesh one.',
		'Exactly what you would expect. I love the look and feel of this pillow.',
		'10 Stars, I would highly recommend this item. We love this blanket.',
		'Great little egg masher. We have had it for a few months now and it\'s been',
		'As advertised. Easy to use. Love the colors. Also, the dimensions are just',
		'it is exactly as pictured. I love the look and feel of this pillow.',
		'Fantastic, just what I wanted!!! I love the look and feel of this pillow.',
		'Supposed to come with extra hardware. The only problem is that it\'s not really a vacuum,',
		'Easy to use. Seems to be an easy way to make a smoothie for a small person.',
		'Not what I am accustomed to. The only reason I gave it 4 stars is because I just can',
		'Nice product & seller. Yes, it is a very thin plastic piece. However, the handle has',
		'So good we bought the second set and they look just like the first one. I am not sure',
		'Wonderful aroma throughout the house. The only problem is that it\'s kind of hard to put',
		'Received in no time flat. I love the look. My husband likes it too.',
		'These are okay they are not as nice as the ones I got from my local store but they are',
		'gave them away - thought it would be a good addition to my kitchen! I am very',
		'Product is fine, wish it had an option to have a separate handle.',
		'Awesome is all I have to say, the materials are good, and the quality is decent.',
		'Way too heavy for everyday use. The only reason I gave it 4 stars is because the one we',
		'Well made, works great. We have had the wooden part of the cup for a couple of',
		'Bought these to go with my old suction cups. I also love that they are removable and',
		'Just as expected! Looks great and has the design to make it a nice place for the baby to',
		'Awful experience, everything stuck, cooked evenly. The only problem is that it\'s not really a',
		'Does a great job of keeping the suction on. I also love that it doesn\'t slide.',
		'SHEET COLOR IS NICE BUY FOR MY SIZE FOR MY TOWN.',
		'Save $ on these instead of the original shipping box. I will keep my money!Very pretty.',
		'These towels are real nice, and I love the feel of them. They have a nice feel',
		'Works great, easy to clean, and easy to clean. I will be purchasing more!Very pretty',
		'Alright for the price,but was a little disappointed with the quality of the product.',
		'Great quality, beautiful color. We have had the same one for a few months now and love',
		'I put paper in one corner of the cabinet and put a piece of paper in the other corner.',
		'Great stickers but a little small. The only reason I gave it 4 stars is because the one in',
		'Great quality and the fabric looks great. It is thick enough to hold a small amount of coffee',
		'Perfect. They do exactly what I need them to do. I will keep them for a long time',
		'Love this movie & the time it took to finish. I will keep my review for the next time',
	);

	/**
	 * Generate product reviews.
	 *
	 * @param bool $save Save the object before returning or not.
	 * @return \WP_Comment|false Review object with data populated or false when failed.
	 */
	public static function generate( $save = true ) {
		parent::maybe_initialize_generators();

		$customer   = self::get_existing_customer();
		$product_id = self::get_random_product_id();

		if ( ! $customer instanceof \WC_Customer || ! $product_id ) {
			return false;
		}

		$review = array(
			'comment_post_ID'      => $product_id,
			'user_id'              => $customer->get_id(),
			'comment_author'       => $customer->get_display_name(),
			'comment_author_email' => $customer->get_email(),
			'comment_content'      => self::$fake_reviews[ array_rand( self::$fake_reviews ) ],
			'comment_type'         => 'review',
			'comment_approved'     => 1,
			'rating'               => wp_rand( 1, 5 ),
		);
		$result = wp_insert_comment( $review );

		if ( ! $result ) {
			return false;
		}

		if ( $save ) {
			wc_get_product( $product_id )->save();
		}

		$review = get_comment( $result, 'OBJECT' );

		/**
		 * Action: Review generator returned a new review.
		 *
		 * @since 1.2.0
		 *
		 * @param \WP_Comment $review
		 */
		do_action( 'smoothgenerator_review_generated', $review );

		return $review;
	}

	/**
	 * Create multiple reviews.
	 *
	 * @param int $amount The number of reviews to create.
	 *
	 * @return int[]|\WP_Error
	 */
	public static function batch( $amount ) {
		$amount = self::validate_batch_amount( $amount );
		if ( is_wp_error( $amount ) ) {
			return $amount;
		}

		$review_ids = array();

		for ( $i = 1; $i <= $amount; $i++ ) {
			$review       = self::generate();
			$review_ids[] = $review->comment_ID;
		}

		return $review_ids;
	}

	/**
	 *  Get randomly selected existing customer.
	 *
	 * @return \WC_Customer
	 */
	protected static function get_existing_customer() {
		global $wpdb;

		$total_users     = (int) $wpdb->get_var( "SELECT COUNT( * ) FROM {$wpdb->users}" );
		$customer_offset = wp_rand( 0, $total_users );
		$user_id         = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} ORDER BY rand() LIMIT $customer_offset, 1" ); // phpcs:ignore

		return new \WC_Customer( $user_id );
	}

	/**
	 *  Get ID of randomly selected existing product.
	 *
	 * @return int ID of random product.
	 */
	protected static function get_random_product_id() {
		global $wpdb;

		$num_existing_products = (int) $wpdb->get_var(
			"SELECT COUNT( DISTINCT ID )
			FROM {$wpdb->posts}
			WHERE 1=1
			AND post_type='product'
			AND post_status='publish'"
		);

		$product_offset = wp_rand( 0, $num_existing_products );

		$product_id = (int) wc_get_products( array(
			'orderby' => 'rand',
			'limit'   => 1,
			'return'  => 'ids',
			'offset'  => $product_offset,
		))[0];

		if ( ! wc_get_product( $product_id ) instanceof \WC_Product ) {
			return false;
		}

		return $product_id;
	}
}
