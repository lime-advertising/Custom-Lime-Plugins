<?php
function lws_get_search_form_markup()
{
    ob_start(); ?>
    <div class="lime-wp-search-wrapper">
        <form role="search" method="get" class="lime-wp-search-form" action="<?php echo esc_url(home_url('/')); ?>">
            <label class="screen-reader-text" for="lime-wp-search-input">Search</label>
            <input
                type="search"
                id="lime-wp-search-input"
                class="search-field"
                name="s"
                value=""
                placeholder="Start typing..."
                autocomplete="off" />
            <button type="submit" class="search-submit">Search</button>
        </form>
        <!-- needed by AJAX -->
        <div id="lime-wp-search-results" class="lime-wp-search-results" role="listbox" aria-live="polite"></div>
    </div>
<?php
    return ob_get_clean();
}
