<?php defined('SYSPATH') or die('No direct access allowed.');

class Kohana_Model_Comment extends ORM {
    protected $uri;

	protected $_belongs_to = array(
		'author'  => array('model' => 'User')
	);

	// Validation rules
	public function rules(){
        return array(
            'content' => array(
                array('not_empty'),
                array('min_length', array('value:',4)),
                array('max_length', array('value:',65536)),
            ),
        );
    }

    public function filters(){
        return array(
            'content' => array(
                array(array($this,'cleanContent'))
            ),
        );
    }

	// Field labels
    public function labels(){
        return array(
            'username' => 'Username',
            'content' => 'Comment content',
            'captcha' => 'Captcha',
        );
    }

    public function get_author_name () {
        $profile = $this->author->profile;
        return $this->username ?: (
//        $this->author->username ?: Kohana::message('comments', 'anonymous')
        $profile->name ?: Kohana::message('comments', 'anonymous')
        );
    }

    public function get_author_avatar () {
        return $this->author->profile->avatarUri();
    }

    /**
     * Cleaning comment content
     */
    public function cleanContent($content){
        $content = trim($content);
        $content = strip_tags($content);
        return $content;
    }



    /**
     * Count comments while delete comment
     * @return ORM
     */
    public function delete(){
        $id = $this->model_id;
        $model_name = $this->model_name;

        $orm = parent::delete();
        $this->_countComments($id, $model_name);
        $this->_countRating($id, $model_name);
        return $orm;
    }

    /**
     * Count comments while save comment
     * @param Validation $validation
     * @return ORM
     */
    public function save(Validation $validation = NULL){
        $orm = parent::save($validation);
        $this->_countComments();
        $this->_countRating();
        return $orm;
    }

    /**
     * Count comments in related model
     * @param null $id
     * @param null $model_name
     */
    protected function _countComments($id = NULL, $model_name = NULL){
        if(is_null($model_name))
            $model_name = $this->model_name;
        if(is_null($id))
            $id = $this->model_id;
        $model = ORM::factory(ucfirst($model_name), $id);
        if($model && isset($model->comments)){
            $count = DB::select(DB::expr('count(*)'))->from($this->table_name())->where('model_name','=', $model_name)->and_where('model_id','=', $id);
            DB::update($model->table_name())
                ->set(
                    array(
                        'comments' => DB::expr('('. $count .')'),
                    )
                )
                ->where('id', '=', $id)
                ->execute();
        }
    }

    /**
     * Count average record rating
     * @param null $id
     * @param null $model_name
     */
    protected function _countRating($id = NULL, $model_name = NULL){
        if(is_null($model_name))
            $model_name = $this->model_name;
        if(is_null($id))
            $id = $this->model_id;
        $model = ORM::factory(ucfirst($model_name), $id);
        if($model && isset($model->rating)){
            $users_avg = DB::select(array(DB::expr('DISTINCT(author_id)'), 'user_id'), array(DB::expr('AVG(rating)'), 'rate'))->from($this->table_name())->where('model_name','=', $model_name)->and_where('model_id','=', $id)->and_where('rating','>','0')->group_by('user_id');
            $avg = DB::select(DB::expr('AVG(rate)'))->from( DB::expr('('.$users_avg.') avg_users') );
            DB::update($model->table_name())
                ->set(
                    array(
                        'rating' => DB::expr('('. $avg .')'),
                    )
                )
                ->where('id', '=', $id)
                ->execute();
        }
    }


    /**
     * Get URI from related model
     * if related model have getUri() method
     */
    public function getUri(){
        if($this->uri === NULL){
            $this->uri = '';
            $model = ORM::factory(ucfirst($this->model_name), $this->model_id);
            if($model->loaded() && method_exists($model, 'getUri')){
                $this->uri = $model->getUri() . '#comment' . $this->id;
            }
        }
        return $this->uri;
    }
}