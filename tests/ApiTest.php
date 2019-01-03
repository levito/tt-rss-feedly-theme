<?php
use PHPUnit\Framework\TestCase;

set_include_path(dirname(__DIR__) ."/include" . PATH_SEPARATOR .
	dirname(__DIR__) . PATH_SEPARATOR .
	get_include_path());

require_once "autoload.php";

final class ApiTest extends TestCase {

	public function __construct() {
		init_plugins();
		initialize_user_prefs(1);
		set_pref('ENABLE_API_ACCESS', true, 1);

		parent::__construct();
	}

	public function apiCall($args, $method) {
		$_REQUEST = $args;

		$api = new API($args);
		ob_start();
		$api->$method();
		$rv = json_decode(ob_get_contents(), true);
		ob_end_clean();

		$this->assertEquals(API::STATUS_OK, $rv['status']);

		return $rv;
	}

	public function testBasicAuth() {
		$this->assertEquals(true,
			authenticate_user("admin", "password"));
	}

	public function testVersion() {

		$ret = $this->apiCall([], "getVersion");

		$this->assertStringStartsWith(
			VERSION_STATIC,
			$ret['content']['version']);
	}

	public function testLogin() {

		$ret = $this->apiCall(["op" => "login",
			"user" => "admin",
			"password" => "password"], "login");

		$this->assertNotEmpty($ret['content']['session_id']);
	}

	public function testGetUnread() {
		$this->testLogin();
		$ret = $this->apiCall([],"getUnread");

		$this->assertNotEmpty($ret['content']['unread']);
	}

	public function testGetFeeds() {
		$this->testLogin();
		$ret = $this->apiCall([], "getFeeds");

		$this->assertInternalType('array', $ret['content']);

		$this->assertEquals("http://tt-rss.org/forum/rss.php",
			$ret['content'][0]['feed_url']);

	}

	public function testGetCategories() {
		$this->testLogin();
		$ret = $this->apiCall([], "getCategories");

		$this->assertInternalType('array', $ret['content']);

		$this->assertGreaterThanOrEqual(2, sizeof($ret['content']));

		foreach ($ret['content'] as $cat) {

			$this->assertNotEmpty($cat['title']);
			$this->assertNotNull($cat['id']);
			$this->assertGreaterThanOrEqual(0, $cat['unread']);

			$this->assertContains($cat['title'],
				['Special', 'Labels', 'Uncategorized']);
		}
	}

	public function testGetHeadlines() {
		$this->testLogin();
		$ret = $this->apiCall(['feed_id' => -4, 'view_mode' => 'adaptive'], "getHeadlines");

		$this->assertInternalType('array', $ret['content']);

		foreach ($ret['content'] as $hl) {
			$this->assertInternalType('array', $hl);

			$this->assertNotEmpty($hl['guid']);
			$this->assertNotEmpty($hl['title']);
			$this->assertNotEmpty($hl['link']);
		}

		$ret = $this->apiCall(['feed_id' => 1, 'view_mode' => 'all_articles'], "getHeadlines");

		$this->assertInternalType('array', $ret['content']);

		foreach ($ret['content'] as $hl) {
			$this->assertInternalType('array', $hl);

			$this->assertNotEmpty($hl['guid']);
			$this->assertNotEmpty($hl['title']);
			$this->assertNotEmpty($hl['link']);
		}
	}

	public function testArticle() {

		$this->testLogin();
		$ret = $this->apiCall(['feed_id' => -4], "getHeadlines");

		$this->assertInternalType('array', $ret['content'][0]);
		$article_id = $ret['content'][0]['id'];
		$title = $ret['content'][0]['title'];

		$ret = $this->apiCall(['article_id' => $article_id], "getArticle");

		$this->assertInternalType('array', $ret['content']);
		$this->assertNotEmpty($ret['content'][0]['content']);
		$this->assertEquals($title, $ret['content'][0]['title']);
	}

	public function testCounters() {

		$this->testLogin();
		$ret = $this->apiCall(['output_mode' => 'flc'], "getCounters");

		$this->assertInternalType('array', $ret['content']);

		foreach ($ret['content'] as $ctr) {
			$this->assertInternalType('array', $ctr);

			$this->assertNotNull($ctr['id']);
			$this->assertGreaterThanOrEqual(0, $ctr['counter']);
		}
	}

	public function testGetConfig() {

		$this->testLogin();
		$ret = $this->apiCall([], "getConfig");

		$this->assertInternalType('array', $ret['content']);

		foreach ($ret['content'] as $k => $v) {
			$this->assertInternalType('string', $k);
			$this->assertNotEmpty($k);
		}
	}

	public function testBasicPrefs() {

		$this->testLogin();
		$ret = $this->apiCall(['pref_name' => 'ENABLE_API_ACCESS'], "getPref");
		$this->assertEquals(1, $ret['content']['value']);

		set_pref('ENABLE_API_ACCESS', false, 1);

		$ret = $this->apiCall(['pref_name' => 'ENABLE_API_ACCESS'], "getPref");
		$this->assertEquals(0, $ret['content']['value']);

		set_pref('ENABLE_API_ACCESS', true, 1);

		$ret = $this->apiCall(['pref_name' => 'ENABLE_API_ACCESS'], "getPref");
		$this->assertEquals(1, $ret['content']['value']);
	}

	public function testFeedTree() {

		$this->testLogin();
		$ret = $this->apiCall([], "getFeedTree");
		$this->assertInternalType('array', $ret['content']);

		// root
		foreach ($ret['content'] as $tr) {
			$this->assertInternalType('array', $tr);

			$this->assertInternalType('array', $tr['items']);

			// cats
			foreach ($tr['items'] as $cr) {
				$this->assertInternalType('array', $cr['items']);

				$this->assertNotEmpty($cr['id']);
				$this->assertNotEmpty($cr['name']);

				// feeds
				foreach ($cr['items'] as $fr) {
					$this->assertNotEmpty($fr['id']);
					$this->assertNotEmpty($fr['name']);
				}
			}
		}
	}


	public function testLabels() {
		// create label

		Labels::create('Test', '', '', 1);

		$this->testLogin();
		$ret = $this->apiCall([], "getLabels");
		$this->assertInternalType('array', $ret['content']);

		$this->assertEquals('Test', $ret['content'][0]['caption']);
		$label_feed_id = $ret['content'][0]['id'];
		$label_id = Labels::feed_to_label_id($label_feed_id);

		$this->assertLessThan(0, $label_feed_id);
		$this->assertGreaterThan(0, $label_id);

		// assign/remove label to article

		$ret = $this->apiCall(['feed_id' => -4, 'view_mode' => 'adaptive'], "getHeadlines");
		$this->assertInternalType('array', $ret['content'][0]);
		$article_id = $ret['content'][0]['id'];

		$ret = $this->apiCall(['article_ids' => $article_id,
			'label_id' => $label_feed_id, "assign" => "true"],
			"setArticleLabel");

		$ret = $this->apiCall(['article_id' => $article_id], "getArticle");
		$this->assertContains($label_feed_id, $ret['content'][0]['labels'][0]);

		$ret = $this->apiCall(['article_ids' => $article_id,
			'label_id' => $label_feed_id, "assign" => "false"],
			"setArticleLabel");

		$ret = $this->apiCall(['article_id' => $article_id], "getArticle");
		$this->assertEmpty($ret['content'][0]['labels']);

		// clean up and check

		Labels::remove($label_id, 1);

		$ret = $this->apiCall([], "getLabels");
		$this->assertEmpty($ret['content']);
	}


}
