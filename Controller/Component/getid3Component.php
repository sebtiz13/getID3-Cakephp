<?php
class getid3Component extends Component
{
    public $errors = array();
    public $getID3;

    public function __construct()
    {
        set_time_limit(20*3600);
        ignore_user_abort(false);
    }

    public function startup(Controller $controller)
    {
        $this->controller = $controller;

        // Initialize getID3 engine
        App::import('Vendor', 'getID3/getid3');
        $this->getID3 = new getID3;
    }

    private function error($text)
    {
        if ( !is_array($text) )
            array_push($this->errors, $text);
        else {
            foreach ( $text as $t ) {
                array_push($this->errors, $t);
            }
        }
    }

    public function extract($filename)
    {
        $this->getID3->setOption(array('encoding' => Configure::read('App.encoding')));

        // Analyze file and store returned data in $ThisFileInfo
        $ThisFileInfo = $this->getID3->analyze($filename);

        App::import('Vendor', 'getID3/getid3.lib');
        getid3_lib::CopyTagsToComments($ThisFileInfo);

        return $ThisFileInfo;
    }

    public function read($filename) {
        $ThisFileInfo = $this->extract($filename);
        foreach (array('tags', 'tags_html', 'id3v2', 'id3v1', 'quicktime', 'warning') as $value) unset($ThisFileInfo[$value]);
        return $ThisFileInfo;
    }

    public function getId3Clean($filename)
    {
        $info = $this->read($filename);

        $id3 = array();
        foreach ( $info['tags'] as $tag )
        {
            foreach ( $tag as $key => $val )
            {
                if ( empty($id3[$key]) )
                {
                    $id3[$key] = $val[0];
                }
                else
                {
                    if ( strlen($val[0]) > strlen($id3[$key]) )
                    {
                        $id3[$key] = $val[0];
                    }
                }
            }
        }
        return $id3;
    }

    public function getCustomTags($filename, $tags = array())
    {
        $id3 = $this->getId3Clean($filename);
        $vars = array(
            'description'   => 'content_group_description',
            'set'           => 'part_of_a_set'
        );
        $vars = array_merge($vars, $tags);

        foreach ( $vars as $k => $v )
        {
            if ( !empty($id3[$v]) )
            {
                $id3[$k] = $id3[$v];
                unset($id3[$v]);
            }
        }
        return $id3;
    }

    public function write($filename, $data)
    {
        $this->getID3->setOption(array('encoding'=>Configure::read('App.encoding')));

        App::import('Vendor', 'getID3/write');

        // Initialize getID3 tag-writing module
        $tagwriter = new getid3_writetags;

        //$tagwriter->filename       = '/path/to/file.mp3';
        $tagwriter->filename       = $filename;
        $tagwriter->tagformats     = array('id3v1', 'id3v2.3');

        // set various options (optional)
        $tagwriter->overwrite_tags = true;
        $tagwriter->tag_encoding   = Configure::read('App.encoding');
        $tagwriter->remove_other_tags = true;

        // populate data array
        $TagData['title'][]     = !empty($data['title'])    ? $data['title'] : null;
        $TagData['artist'][]    = !empty($data['artist'])   ? $data['artist'] : null;
        $TagData['album'][]     = !empty($data['album'])    ? $data['album'] : null;
        $TagData['year'][]      = !empty($data['year'])     ? $data['year'] : null;
        $TagData['genre'][]     = !empty($data['genre'])    ? $data['genre'] : null;
        $TagData['comment'][]   = !empty($data['comment'])  ? $data['comment'] : null;
        $TagData['track'][]     = !empty($data['track'])    ? $data['track'] : null;

        if ( !empty($data['images']) ) {
            $image_types = array('cover' => 3, 'back' => 4, 'cd' => 6);
            if ( is_array($data['images']) ) {
                $i = 0;
                foreach ( $data['images'] as $image ) {
                    if ( isset($image_types[$image['type']]) ) {
                        $TagData['attached_picture'][$i]['data'] = file_get_contents($image['image']);
                        $TagData['attached_picture'][$i]['picturetypeid'] = $image_types[$image['type']];
                        $TagData['attached_picture'][$i]['mime'] = $image['mime'];
                        $TagData['attached_picture'][$i]['description'] = isset($image['description']) ? $image['description'] : null;
                        $i++;
                    }
                }
            }
        }

        $tagwriter->tag_data = $TagData;

        // write tags
        if ($tagwriter->WriteTags()) {
            if (!empty($tagwriter->warnings)) {
                $this->error($tagwriter->warnings);
            }
            return true;
        } else {
            $this->error($tagwriter->errors);
            return false;
        }
    }
}
