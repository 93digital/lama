<?php
/**
 * Pagination template
 *
 * This module can be override using the `lama_pagination` filters.
 *
 * @used by:
 *  - public static function pagination
 *
 * @package Lama
 */

global $current_page, $show_ends, $first_page, $args, $total_pages, $last_page, $container_name;
?>
<nav class="pagination">
<?php if ( $current_page > 1 && $show_ends ) : ?>
  <a href="<?php echo esc_url( $first_page ); ?>" class="prev page-numbers" title="Go to first page">&laquo;</a>
<?php endif; ?>

<?php echo paginate_links( $args ); ?>

<?php // display last link ?>
<?php if ( $current_page < $total_pages && $show_ends ) : ?>
  <a href="<?php echo esc_url( $last_page ); ?>" class="next page-numbers" title="Go to last page">&raquo;</a>
<?php endif; ?>
</nav>
