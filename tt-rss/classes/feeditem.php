<?php
abstract class FeedItem {
	abstract function get_id();
	abstract function get_date();
	abstract function get_link();
	abstract function get_title();
	abstract function get_description();
	abstract function get_content();
	abstract function get_comments_url();
	abstract function get_comments_count();
	abstract function get_categories();
	abstract function get_enclosures();
	abstract function get_author();
	abstract function get_language();
}

