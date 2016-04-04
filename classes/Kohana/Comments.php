<?php
class Kohana_Comments {
	static function factory (ORM $model) {
		return new static($model);
	}

	static function render (ORM $model) {
		echo static::factory($model)->comments_view();
	}

	static function form (ORM $model, $id = null) {
		echo static::factory($model)->form_view($id);
	}

	static function get (ORM $model) {
		return static::factory($model)->get_comments();
	}

	/**
	 * @var ORM
	 */
	protected $model;
	/**
	 * @var Auth
	 */
	protected $auth;

    /**
     * Comnstructor
     * @param ORM $model
     */
    public function __construct(ORM $model) {
		$this->auth  = Auth::instance();
		$this->model = array (
			'id'   => $model->id,
//			'name' => $model->object_name(),
			'name' => Comments::object_name($model),
			'object' => $model,
		);
	}

    /**
     * Object name getter
     * @param $model
     * @return string
     */
    public static function object_name($model){
        return  lcfirst(substr(get_class($model), 6));
    }

    /**
     * Getting comment list
     * @return $this
     */
    public function get_comments () {
		return ORM::factory('Comment')
			->where('model_id',   '=', $this->model['id'])
			->where('model_name', '=', $this->model['name']);
	}

    /**
     * Rendering comment list
     * @return View
     */
    public function comments_view () {
		$view = new View('comments/comments');
		$view->comments = $this->get_comments()->find_all();
		return $view;
	}

    /**
     * Render comment form
     * @param null $id
     * @return string|View
     */
    public function form_view ($id = null) {
		if (!$this->can_comment()) return $this->cant_comment_view();

		$view = new View('comments/form');

		$comment = $this->find_comment($id);
		$errors  = $this->try_save_comment($comment);

		$view->set('comment', $comment);
		$view->set('show_username_field', $this->can_set_username());
		$view->set('show_rating_field', $this->can_set_rating());
		$view->set('errors', $this->render_errors($errors));
		return $view;
	}

    /**
     * Rendering errors while save comment
     * @param $errors
     * @return string
     */
    protected function render_errors ($errors) {
		if (!$errors) return '';
		
		$view = new View('comments/error');
		$view->message = __(Kohana::message('comments', 'validation_failed'));
		$view->errors  = $errors;
		return (string) $view;
	}

    /**
     * Saving comment
     * @param Model_Comment $comment
     * @return array|null
     */
    protected function try_save_comment (Model_Comment $comment) {
		$form = $this->get_post();

		if ($form['content'] === null || !$this->can_comment()) return null;

        try{
            $validation = Validation::factory($form);

            if(!$this->auth->logged_in()){
                $validation->rules('username', array(
                    array('not_empty'),
                    array('min_length', array('value:',3)),
                    array('max_length', array('value:',32)),
                ))->rules('captcha', array(
                    array('not_empty'),
                    array('Comments::checkCaptcha',array(':value', ':validation', ':field'))
                ))->labels($comment->labels());

            }
            $comment->values($form)->check($validation);
            $this->set_modified($comment)->save();
            $this->redirect($comment);
        }
        catch(ORM_Validation_Exception $e){
            return $e->errors('validation');
        }
	}

    /**
     * Setting comment modify time
     * @param Model_Comment $comment
     * @return Model_Comment
     */
    protected function set_modified (Model_Comment $comment) {
		if ($comment->id) {
			$comment->modified = DB::expr('NOW()');
		}
		return $comment;
	}

    /**
     * Redorecting after save comment
     * @param Model_Comment $comment
     */
    protected function redirect (Model_Comment $comment) {
        header("Location: ". URL::site(Request::current()->uri()) );
        die();
	}

    /**
     * Getting comment record
     * @param $id
     * @return ORM
     * @throws Kohana_Exception
     */
    protected function find_comment ($id) {
		if (!$id) {
			$comment = $this->create_comment();
		} else {
			$comment = ORM
				::factory('comment', $id)
				->with('author')
				->find();
			if (!$comment) {
				throw new Kohana_Exception("No comment with id «{$id}»");
			}
		}
		return $comment;
	}

    /**
     * Creating comment record
     * @return ORM
     */
    protected function create_comment () {
		$comment = ORM::factory('Comment');
		$this->set_comment_author($comment);
		$this->set_comment_model($comment);
		return $comment;
	}

    /**
     * Setting comment author
     * @param Model_Comment $comment
     */
    protected function set_comment_author(Model_Comment $comment) {
		if ($this->auth->logged_in()) {
			$comment->author = $this->auth->get_user();
		}
	}

    /**
     * Setting comment related model
     * @param Model_Comment $comment
     */
    protected function set_comment_model(Model_Comment $comment) {
		$comment->model_id = $this->model['id'];
		$comment->model_name = $this->model['name'];
	}

    /**
     * Check if user can view comments
     * @return string
     */
    protected function cant_comment_view () {
		return '';
	}

    /**
     * Check if user can comment
     * @return bool
     */
    protected function can_comment() {
		return true;
	}

    /**
     * Check if user authenticated
     * @return bool
     */
    protected function can_set_username() {
		return !$this->auth->logged_in();
	}

    /**
     * Check if user authenticated
     * @return bool
     */
    protected function can_set_rating() {
		return $this->auth->logged_in() && isset($this->model['object']->rating);
	}

    /**
     * Parsing POST array
     * @return array
     */
    protected function get_post () {
		$post = Arr::extract($_POST, array('comment-username','comment-content', 'rating', 'captcha'));
		return array(
			'username' => $post['comment-username'],
			'content'  => $post['comment-content'],
			'rating'  => $post['rating'],
			'captcha'  => $post['captcha'],
		);
	}

    /**
     * Check captcha entered and valid (if needed)
     * @param string $value
     * @param Validation $validation
     * @param string $field
     * @return bool
     */
    public static function checkCaptcha($value, $validation, $field){
        if(!Auth::instance()->logged_in()){
            if(!Captcha::valid($value)){
                $validation->error($field, 'captcha_mismatch');
            }
        }
        return true;
    }

    /**
     * Count comment objects array
     * than has not been moderated before
     * @return int
     */
    public static function countNotModerated(){
        $count = ORM::factory('Comment')->where('moderated', '=', 0)->count_all();
        return $count;
    }

    /**
     * Find comment objects array
     * than has not been moderated before
     * @return array
     */
    public static function findNotModerated(){
        $comments = ORM::factory('Comment')->where('moderated', '=', 0)->find_all()->as_array('id');
        return $comments;

    }

    /**
     * Check all not moderated comments as moderated
     * @return int
     */
    public static function setAllModerated(){
        return DB::update('comments')->set(array('moderated'=>1))->where('moderated', '=', 0)->execute();
    }

    /**
     * Check all selected
     * @param array $ids
     * @return object
     */
    public static function setModerated(Array $ids){
        return DB::update('comments')->set(array('moderated'=>1))->where('moderated', '=', 0)->and_where('id','IN',$ids)->execute();
    }

    /**
     * Delete all selected comment
     * @param array $ids
     * @return object
     */
    public static function delSelected(Array $ids){
        $count = ORM::factory('Comment')->where('id','IN',$ids)->count_all();
        $comments = ORM::factory('Comment')->where('id','IN',$ids)->find_all();
        foreach($comments as $comment)
            $comment->delete();
        return $count;
    }


    /**
     * List of rating options for select field
     * @return array
     */
    public static function ratingOptions(){
        $options = array(
            0 => __('not rate'),
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5',
        );

        return $options;
    }
}