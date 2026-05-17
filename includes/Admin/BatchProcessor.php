<?php

namespace WC\SmoothGenerator\Admin;

use Automattic\WooCommerce\Internal\BatchProcessing\{ BatchProcessorInterface, BatchProcessingController };
use WC\SmoothGenerator\Router;

/**
 * Class BatchProcessor.
 *
 * A class for asynchronously generating batches of objects using WooCommerce's internal batch processing tool.
 * (This might break if changes are made to the tool.)
 */
class BatchProcessor implements BatchProcessorInterface {
	/**
	 * The key used to store the state of the current job in the options table.
	 */
	const OPTION_KEY = 'smoothgenerator_async_job';

	/**
	 * Get the state of the current job.
	 *
	 * @return ?AsyncJob Null if there is no current job.
	 */
	public static function get_current_job() {
		$current_job = get_option( self::OPTION_KEY, null );

		if ( ! $current_job instanceof AsyncJob && wc_get_container()->get( BatchProcessingController::class )->is_enqueued( self::class ) ) {
			wc_get_container()->get( BatchProcessingController::class )->remove_processor( self::class );
		} elseif ( $current_job instanceof AsyncJob && ! wc_get_container()->get( BatchProcessingController::class )->is_enqueued( self::class ) ) {
			self::delete_current_job();
			$current_job = null;
		}

		return $current_job;
	}

	/**
	 * Create a new AsyncJob object.
	 *
	 * @param string $generator_slug The slug identifier of the generator to use.
	 * @param int    $amount         The number of objects to generate.
	 * @param array  $args           Additional args for object generation.
	 *
	 * @return AsyncJob|\WP_Error
	 */
	public static function create_new_job( string $generator_slug, int $amount, array $args = array() ) {
		if ( self::get_current_job() instanceof AsyncJob ) {
			return new \WP_Error(
				'smoothgenerator_async_job_already_exists',
				'Can\'t create a new Smooth Generator job because one is already in progress.'
			);
		}

		$job = new AsyncJob( array(
			'generator_slug' => $generator_slug,
			'amount'         => $amount,
			'args'           => $args,
			'pending'        => $amount,
		) );

		update_option( self::OPTION_KEY, $job, false );

		wc_get_container()->get( BatchProcessingController::class )->enqueue_processor( self::class );

		return $job;
	}

	/**
	 * Update the state of the current job.
	 *
	 * @param int $processed The amount to change the state values by.
	 *
	 * @return AsyncJob|\WP_Error
	 */
	public static function update_current_job( int $processed ) {
		$current_job = self::get_current_job();

		if ( ! $current_job instanceof AsyncJob ) {
			return new \WP_Error(
				'smoothgenerator_async_job_does_not_exist',
				'There is no Smooth Generator job to update.'
			);
		}

		$current_job->processed += $processed;
		$current_job->pending    = max( $current_job->pending - $processed, 0 );

		update_option( self::OPTION_KEY, $current_job, false );

		return $current_job;
	}

	/**
	 * Delete the AsyncJob object.
	 *
	 * @return bool
	 */
	public static function delete_current_job() {
		wc_get_container()->get( BatchProcessingController::class )->remove_processor( self::class );
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Get a user-friendly name for this processor.
	 *
	 * @return string Name of the processor.
	 */
	public function get_name(): string {
		return 'Smooth Generator';
	}

	/**
	 * Get a user-friendly description for this processor.
	 *
	 * @return string Description of what this processor does.
	 */
	public function get_description(): string {
		return 'Generates various types of WooCommerce data objects with randomized data for use in testing.';
	}

	/**
	 * Get the total number of pending items that require processing.
	 * Once an item is successfully processed by 'process_batch' it shouldn't be included in this count.
	 *
	 * Note that the once the processor is enqueued the batch processor controller will keep
	 * invoking `get_next_batch_to_process` and `process_batch` repeatedly until this method returns zero.
	 *
	 * @return int Number of items pending processing.
	 */
	public function get_total_pending_count(): int {
		$current_job = self::get_current_job();

		if ( ! $current_job instanceof AsyncJob ) {
			return 0;
		}

		return $current_job->pending;
	}

	/**
	 * Returns the next batch of items that need to be processed.
	 *
	 * A batch item can be anything needed to identify the actual processing to be done,
	 * but whenever possible items should be numbers (e.g. database record ids)
	 * or at least strings, to ease troubleshooting and logging in case of problems.
	 *
	 * The size of the batch returned can be less than $size if there aren't that
	 * many items pending processing (and it can be zero if there isn't anything to process),
	 * but the size should always be consistent with what 'get_total_pending_count' returns
	 * (i.e. the size of the returned batch shouldn't be larger than the pending items count).
	 *
	 * @param int $size Maximum size of the batch to be returned.
	 *
	 * @return array Batch of items to process, containing $size or less items.
	 */
	public function get_next_batch_to_process( int $size ): array {
		$current_job = self::get_current_job();
		$max_batch   = self::get_default_batch_size();

		if ( ! $current_job instanceof AsyncJob ) {
			$current_job = new AsyncJob();
		}

		$amount = min( $size, $current_job->pending, $max_batch );

		// The batch processing controller counts items in the array to determine if there are still pending items.
		if ( $amount < 1 ) {
			return array();
		}

		return array(
			'generator_slug' => $current_job->generator_slug,
			'amount'         => $amount,
			'args'           => $current_job->args,
		);
	}

	/**
	 * Process data for the supplied batch.
	 *
	 * This method should be prepared to receive items that don't actually need processing
	 * (because they have been processed before) and ignore them, but if at least
	 * one of the batch items that actually need processing can't be processed, an exception should be thrown.
	 *
	 * Once an item has been processed it shouldn't be counted in 'get_total_pending_count'
	 * nor included in 'get_next_batch_to_process' anymore (unless something happens that causes it
	 * to actually require further processing).
	 *
	 * @throw \Exception Something went wrong while processing the batch.
	 *
	 * @param array $batch Batch to process, as returned by 'get_next_batch_to_process'.
	 */
	public function process_batch( array $batch ): void {
		list( 'generator_slug' => $slug, 'amount' => $amount, 'args' => $args ) = $batch;

		$result = Router::generate_batch( $slug, $amount, $args );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );
		}

		self::update_current_job( count( $result ) );
	}

	/**
	 * Default (preferred) batch size to pass to 'get_next_batch_to_process'.
	 * The controller will pass this size unless it's externally configured
	 * to use a different size.
	 *
	 * @return int Default batch size.
	 */
	public function get_default_batch_size(): int {
		$current_job = self::get_current_job() ?: new AsyncJob();
		$generator   = Router::get_generator_class( $current_job->generator_slug );

		if ( is_wp_error( $generator ) ) {
			return 0;
		}

		return $generator::MAX_BATCH_SIZE;
	}
}
