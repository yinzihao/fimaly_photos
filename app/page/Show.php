<?php
namespace UView;

use CLib\pager;
use Core\Exception\PageException404;
use Core\Page;
use ULib\CountMessage;
use ULib\Gallery;
use ULib\GalleryComment;
use ULib\ListGallery;
use ULib\PictureComment;
use ULib\Picture;
use ULib\Post;
use ULib\PostComment;
use ULib\Tag;
use ULib\User;
use ULib\UserManagement;

class Show extends Page{
	/**
	 * @var \ULib\Theme
	 */
	private $theme;

	function __construct(){
		parent::__construct();
		if(strpos(\u()->getUriInfo()->getPath(), "/Show") === 0){
			$this->__load_404();
			exit;
		} else{
			$this->theme = \theme();
		}
		\c_lib()->load('pager');
	}

	public function home(){
		$this->__lib('Picture', 'Gallery', 'UserManagement');
		$pic = new Picture();
		$g = new Gallery();
		$um = new UserManagement();
		$this->__view("Home/header.php");
		$p_list = $pic->select_new_pic(18);
		$g_list = $g->select_new_gallery(8, true);
		$u_list = $um->get_new_users(5);
		$this->__view("Show/home.php", [
			'pic_list' => $p_list,
			'gallery_list' => $g_list,
			'user_list' => $u_list
		]);
		$this->__view("Home/footer.php");
	}


	/**
	 * @param int $id  图片ID
	 * @param int $c_p 页数，默认使用0，根据其进行排序
	 */
	public function picture($id = 0, $c_p = 0){
		$id = intval($id);
		$c_p = intval($c_p);
		$n = func_num_args();
		if($n < 1 || $n > 2 || $c_p < 0 || $id < 1){
			$this->__load_404();
			return;
		}
		$this->__lib('Picture', 'PictureComment');
		$pic = new Picture();
		$info = $pic->get_pic($id);
		if(!is_array($info)){
			$this->__load_404();
		} else{
			$this->theme->setTitle("第 {$info['pic_id']} 号图片");
			$this->__view("Home/header.php");
			$this->__view("Show/picture.php", [
				'info' => $info,
				'CommentData' => new PictureComment($info['pic_id'], $c_p, $info)
			]);
			$this->__view("Home/footer.php");
		}
	}

	/**
	 * @param int $id  图集ID
	 * @param int $c_p 页数，默认使用0，根据其进行排序
	 */
	public function gallery($id = 0, $c_p = 0){
		$id = intval($id);
		$c_p = intval($c_p);
		$n = func_num_args();
		if($n < 1 || $n > 2 || $c_p < 0 || $id < 1){
			$this->__load_404();
			return;
		}
		$this->__lib("Gallery", 'GalleryComment');
		$g = new Gallery($id);
		$info = $g->getInfo(true);
		if(!is_array($info) || !isset($info['gallery_status']) || ($info['gallery_status'] != 1 && !(is_login() && $info['user_id'] == \login_user()->getId() && strtolower(\req()->get('preview')) == 'true'))){
			$this->__load_404();
		} else{
			$this->theme->setTitle($info['gallery_title'] . " [图集]");
			$this->__view("Home/header.php");
			$this->__view("Show/gallery.php", [
				'gallery' => $g,
				'info' => $info,
				'CommentData' => new GalleryComment($id, $c_p, $info)
			]);
			$this->__view("Home/footer.php");
		}
	}

	public function user($user_name = ''){
		$user = User::getUser($user_name);
		if($user === NULL){
			$this->__load_404();
		} else{
			$this->__lib("CountMessage");
			$count = new CountMessage();
			$this->theme->setTitle($user->getAliases() . "(" . $user->getName() . ") 的主页");
			$this->__view("Home/header.php");
			$this->__view("Show/user.php", [
				'user' => $user,
				'count' => $count->getUserCount($user)
			]);
			$this->__view("Home/footer.php");
		}
	}

	public function post($name = NULL, $c_p = 0){
		$this->__lib('Post', 'PostComment');
		$post = new Post(NULL, $name);
		$info = $post->getInfo();
		if(!isset($info['post_id']) || ($info['post_status'] != 1 && !(is_login() && $info['post_users_id'] == \login_user()->getId() && strtolower(\req()->get('preview')) == 'true'))){
			$this->__load_404();
		} else{
			$this->theme->setTitle($info['post_title'] . " - 文章");
			$this->__view("Home/header.php");
			$this->__view("Show/post.php", [
				'info' => $info,
				'user' => $post->getPostUser(),
				'CommentData' => new PostComment($info['post_id'], $c_p, $info)
			]);
			$this->__view("Home/footer.php");
		}
	}

	public function post_list($page = 0){
		$this->__lib('Post');
		$post = new Post();
		$post->setPager($page, 2);
		$list = $post->getPublicList();
		$count = $post->getCount();
		if(empty($list) || $count['page'] > $count['max']){
			$this->__load_404();
			return;
		}
		$this->theme->setTitle("文章列表");
		$this->__view("Home/header.php");
		$this->__view("Show/post_list.php", [
			'list' => $list,
			'count' => $count
		]);
		$this->__view("Home/footer.php");
	}

	public function tag_list($tag_name = '', $page = 0){
		try{
			//首先加载图集
			$this->tag_gallery_list($tag_name, $page);
		} catch(PageException404 $ex){
			//如果报404异常，执行图片标签列表
			$this->tag_picture_list($tag_name, $page);
		}
	}

	public function tag_gallery_list($tag_name = '', $page = 0){
		$this->__lib("tag_query/Gallery");
		$gallery = new \ULib\tag_query\Gallery($tag_name);
		$count = $gallery->get_count();
		$pager = new pager($count, 16, $page);
		if($page > $pager->getAllPage()){
			$this->__load_404();
			return;
		}
		$pager->setLinkCreator(function ($p) use ($tag_name){
			/**
			 * @var \ULib\Router $router
			 */
			$router = \lib()->using('router');
			return get_url($router->getLink('tag_type_list_pager', $tag_name, 'gallery', $p));
		});
		$list = $gallery->query($pager->get_limit());
		if(empty($list)){
			throw new PageException404();
		}
		$this->theme->setTitle($tag_name . "- 第{$pager->getCurrentPage()}页 - 图集标签");
		$this->__view("Home/header.php");
		$this->__view("Show/gallery_tag.php", [
			'list' => $list,
			'number' => $pager->getCurrentPage(),
			'tag_name' => $tag_name,
			'pager' => $pager->get_pager()
		]);
		$this->__view("Home/footer.php");
		return NULL;
	}

	public function tag_picture_list($tag_name = '', $page = 0){
		$this->__lib("tag_query/Picture");
		$pictures = new \ULib\tag_query\Picture($tag_name);
		$count = $pictures->get_count();
		$pager = new pager($count, 30, $page);
		if($page > $pager->getAllPage()){
			$this->__load_404();
			return;
		}
		$list = $pictures->query($pager->get_limit());
		if(empty($list)){
			throw new PageException404();
		}
		$pager->setLinkCreator(function ($p) use ($tag_name){
			/**
			 * @var \ULib\Router $router
			 */
			$router = \lib()->using('router');
			return get_url($router->getLink('tag_type_list_pager', $tag_name, 'picture', $p));
		});
		$this->theme->setTitle($tag_name . "- 第{$pager->getCurrentPage()}页 - 图片标签");
		$this->__view("Home/header.php");
		$this->__view("Show/pictures_tag.php", [
			'list' => $list,
			'number' => $pager->getCurrentPage(),
			'tag_name' => $tag_name,
			'pager' => $pager->get_pager()
		]);
		$this->__view("Home/footer.php");
		return NULL;
	}

	public function tag(){
		$this->__lib("Tag");
		$tag = new Tag();
		$tags = $tag->get_hot_tags([0, 50]);
		$font_size = function ($count){
			$m = $count / 5;
			if($m > 10){
				$m = 10;
			}
			$s = $m * 2 + 16;
			return $s;
		};
		$this->theme->setTitle("热门标签");
		$this->__view("Home/header.php");
		$this->__view("Show/hot_tags.php", compact('tags', 'font_size'));
		$this->__view("Home/footer.php");
	}

	public function time_line(){
		if(!is_login()){
			redirect_to_login();
		}
		$this->theme->setTitle("时间线");
		$this->theme->footer_add($this->theme->js(['src' => get_style("time_line.js")]));
		$this->theme->footer_add($this->theme->js(['src' => get_js_url("jquery.form.js")]));
		$this->__view("Home/header.php");
		$this->__view("Show/time_line.php");
		$this->__view("Home/footer.php");
	}

	public function user_gallery_list($user = NULL, $page = 0){
		$page = (int)$page;
		$this->__lib('ListGallery');
		$lg = new ListGallery();
		$lg->setPager($page, 6);
		$pager = [
			'previous' => NULL,
			'next' => NULL
		];
		$list = $lg->getListOfUser($user);
		if(!isset($list[0]) || !reset($list[0])){
			$this->__load_404();
		} else{
			$count = $lg->getCount();
			/**
			 * @var \ULib\Router $router
			 */
			$router = \lib()->using('router');
			if($count['page'] > 1){
				if($count['page'] == 2){
					$pager['previous'] = get_url($router->getLink("user_gallery_list", $user));
				} else{
					$pager['previous'] = get_url($router->getLink("user_gallery_list_pager", $user, $count['page'] - 1));
				}
			}
			if($count['page'] < $count['max']){
				$pager['next'] = get_url($router->getLink("user_gallery_list_pager", $user, $count['page'] + 1));
			}
			$user = User::getUser($user);
			if($page > 0){
				$this->theme->setTitle($user->getName() . " 的图集列表 第{$page}页");
			} else{
				$this->theme->setTitle($user->getName() . " 的图集列表");
			}
			$this->__view("Home/header.php");
			$this->__view("Show/user_header.php", ['user' => $user]);
			$this->__view("Show/gallery_list.php", [
				'list' => $list,
				'pager' => $pager,
				'type' => 'user',
				'number' => $count['page'],
			]);
			$this->__view("Home/footer.php");
		}
	}

	public function gallery_list($page = 0){
		$page = (int)$page;
		$this->__lib('ListGallery');
		$lg = new ListGallery();
		$lg->setPager($page, 6);
		$pager = [
			'previous' => NULL,
			'next' => NULL
		];
		$list = $lg->getList();

		if(!isset($list[0]) || !reset($list[0])){
			$this->__load_404();
		} else{
			$count = $lg->getCount();
			/**
			 * @var \ULib\Router $router
			 */
			$router = \lib()->using('router');
			if($count['page'] > 1){
				if($count['page'] == 2){
					$pager['previous'] = get_url($router->getLink("gallery_list"));
				} else{
					$pager['previous'] = get_url($router->getLink("gallery_list_pager", $count['page'] - 1));
				}
			}
			if($count['page'] < $count['max']){
				$pager['next'] = get_url($router->getLink("gallery_list_pager", $count['page'] + 1));
			}
			if($page > 0){
				$this->theme->setTitle("图集列表 第{$page}页");
			} else{
				$this->theme->setTitle("图集列表");
			}
			$this->__view("Home/header.php");
			$this->__view("Show/gallery_list.php", [
				'list' => $list,
				'pager' => $pager,
				'number' => $count['page'],
				'type' => 'all'
			]);
			$this->__view("Home/footer.php");
		}
	}

	public function pictures(){
		if(func_num_args() > 0){
			throw new PageException404();
		}
		$this->theme->setTitle("分享图片流");
		$this->theme->footer_add($this->theme->js(['src' => get_style("pictures_flow.js")]));
		$this->__view("Home/header.php");
		$this->__view("Show/pictures_list.php");
		$this->__view("Home/footer.php");
	}

	public function search(){
		if(func_num_args() > 0 || !search_func_is_open()){
			throw new PageException404();
		}
		$key_word = req()->get('q');
		$this->theme->setTitle("站内搜索");
		$this->theme->footer_add($this->theme->js(['src' => get_style("search.js")]),99);
		$key_word_json = json_encode($key_word);
		$footer = <<<HTML
<script type="text/javascript">
	search_init({$key_word_json});
</script>
HTML;
		$this->theme->footer_add($footer, 100);
		$this->__view("Home/header.php", ['key_word' => htmlspecialchars($key_word)]);
		$this->__view("Show/search.php", ['key_word' => htmlspecialchars($key_word)]);
		$this->__view("Home/footer.php");
	}
}