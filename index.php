<?php
// 上传错误日志文件
// 请求方法 post
// 参数 log 类型 file
function getIp()
{
    if (isset($_SERVER["HTTP_CLIENT_IP"]) && strcasecmp($_SERVER["HTTP_CLIENT_IP"], "unknown")) {
        $ip = $_SERVER["HTTP_CLIENT_IP"];
    } else {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && strcasecmp($_SERVER["HTTP_X_FORWARDED_FOR"], "unknown")) {
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else {
            if (isset($_SERVER["REMOTE_ADDR"]) && strcasecmp($_SERVER["REMOTE_ADDR"], "unknown")) {
                $ip = $_SERVER["REMOTE_ADDR"];
            } else {
                if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp(
                    $_SERVER['REMOTE_ADDR'],
                    "unknown"
                )
                ) {
                    $ip = $_SERVER['REMOTE_ADDR'];
                } else {
                    $ip = "unknown";
                }
            }
        }
    }
    // ,
    return (explode(",", $ip)[0]);
}
function rendStyle(){
	echo <<<EOT
<style>
    h3{text-align:center}
    table{border-collapse:collapse;min-width:600px;text-align:center;margin:0 auto;}
    th,td{border:1px solid #000000;padding:2px 10px;}
    a{text-decoration:none}
    .red{color: red;}
    .green{color:green;}
    .yellow{color: #ee8d24;}
    .shelve{color: #85139c;}
    button{margin-left: 1rem;width:65px;}
    .float-right {position: fixed;right:0;display:flex;flex-direction:column;margin:10px;background:#369cce2b;}
    .col {margin: 10px;}
</style>
EOT;
}

/**
 * FILE class
 */
class File
{
    private $file;
    private $savePath = self::SAVEPATH;
    private $ignore = ['.', '..', '.gitignore'];

    const SAVEPATH = './log/';


    public function name($name)
    {
        if (empty($_FILES[$name])) {
            throw new InvalidArgumentException('没有上传文件');
        }

        $this->file = $_FILES[$name];
        return $this;
    }
	private function getMD5($content){
		$lastException = null;
		for($i = 5;$i<10;$i++){
			$exp = "/Time:.*\\n((.*\\n){{$i}}TargetSite:.*)/";
			preg_match_all($exp, $content, $rs);
			
			$lastException = array_pop($rs[1]);
		    //echo 	$exp .$lastException;
			if($lastException) break;
		}
        return md5($lastException);
	}
    public function move()
    {
        if (!is_dir($this->savePath)) {
            @mkdir($this->savePath);
        }
  

        $content = file_get_contents($this->file['tmp_name']);
        $size = filesize($this->file['tmp_name']);
        // var_dump($size);die();
        $md5 = $this->getMD5($content);
        if (file_exists($this->savePath.$md5.'.txt')) {
            if($this->increment($md5, $size)) {
                file_put_contents($this->savePath.$md5.'.txt', $content);
            }

        } else {
            // var_dump($this->appendMd5($md5,$size));die();
            $this->appendMd5($md5,$size);
            file_put_contents($this->savePath.$md5.'.txt', $content);
        }
        return $this;
    }

    public function j_move($j_filename)
    {
		//echo $j_filename;
        $content = file_get_contents(File::SAVEPATH.$j_filename);
        $size = filesize(File::SAVEPATH.$j_filename);
        $md5 = $this->getMD5($content);
        $ext = explode('-', $j_filename);
        if (file_exists(File::SAVEPATH.$md5.'.txt')) {
            if($this->increment($md5, $size, $ext[1], date('Y/m/d H:i:s', strtotime($ext[0])), $ext[2])) {
                file_put_contents(File::SAVEPATH.$md5.'.txt', $content);
            }

        } else {
            $this->appendMd5($md5,$size, $ext[1], date('Y/m/d H:i:s', strtotime($ext[0])), $ext[2]);
            file_put_contents(File::SAVEPATH.$md5.'.txt', $content);
        }
        File::rm($j_filename);
        touch(File::SAVEPATH.'installed');
        return $this;
    }

    public static function installed()
    {
        return file_exists(self::SAVEPATH.'installed');
    }


    private function appendMd5($md5,$size = 0, $ip=null, $date=null, $state=0) {
        $size = str_pad($size, 10, '0', STR_PAD_LEFT);
        $date = $date ? $date : date('Y/m/d H:i:s');
        $ip = str_pad($ip ? $ip : getIp(), 15, ' ', STR_PAD_RIGHT);
        file_put_contents($this->savePath.'md5-count.txt', $md5.'-000001-'.$state.'-'.$ip.'-'.$size.'-'.$date.PHP_EOL, FILE_APPEND);
    }

    private function increment($md5, $size=0, $ip=null, $date=null, $state=0) {//+1
        $lines = file($this->savePath.'md5-count.txt');
        $flag = false;
        foreach ($lines as $line => $content) {
            if ($md5 === substr($content, 0, 32)) {
                $flag = true;
                break;
            }
        }
        if (!$flag) {
            //throw new Exception('File same count not exists!');
			//处理以前md5文件死锁未来得及写入的情况
			$this->appendMd5($md5,$size,$ip,$date,$state);
        }else{
			$tmp = explode('-', $content);

			$tmp[1] = str_pad(intval($tmp[1]) + 1, 6, '0', STR_PAD_LEFT);
			$tmp[3] = str_pad($ip ? $ip : getIp(), 15, ' ');
			$tmp[5] = $date ? $date : date('Y/m/d H:i:s');
			$replace = false;
			if ($state) {
				$tmp[2] = $state;
			} elseif ($tmp[2] == Status::RESOLVED) {
				$tmp[2] = Status::UNRESOLVED;
				$tmp[4] = str_pad($size, 10, '0', STR_PAD_LEFT);
				$replace = true;
			}

			$content = join('-', $tmp);
			
			$file = fopen($this->savePath.'md5-count.txt', 'r+');
			fseek($file, $line * 89);
			fwrite($file, $content, 88);
			fclose($file);
			return $replace;
		}
        
    }

    public function getLogList()
    {
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath);
        }

        $files = explode("\n", file_get_contents($this->savePath.'md5-count.txt'));
        array_pop($files);

        $list = [];
        foreach ($files as $file) {
            $list[] = new Log($file);
        }
        sort($list, 0);
        return $list;
    }

    private function ignoreList($file) {
        return in_array($file, $this->ignore);
    }

    public static function rm($name)
    {
        if (file_exists(File::SAVEPATH . $name)) {
            unlink(File::SAVEPATH . $name);
        }
    }
}

final class Status
{
    const UNRESOLVED = 0;
    const RESOLVED = 1;
    const SHELVE = 2;

    public static $colors = [
        self::UNRESOLVED => 'red',
        self::RESOLVED   => 'green',
        self::SHELVE     => 'shelve',
    ];

    public static function getText($num)
    {
        $statusArr = ['未解决', '已解决', '已搁置'];
        return $statusArr[$num];
    }
}
class Log
{
    private $name;
    private $filename;
    private $createAt;
    private $createIp;
    private $status;
    private $fileSize;
    private $repeatCount;
    private $line;
    private $content;

    private $original = [];

    public function __construct($content, $line=0)
    {
        $this->content = $content;
        $this->line = $line;

        $data = explode('-', $content);
        $this->filename = $data[0];

        $this->name = $data[5];
        $this->createAt = $data[5];
        $this->createIp = trim($data[3]);
        $this->status = $data[2];
        $this->repeatCount = intval($data[1]);
        $this->original['fileSize'] = intval($data[4]);
    }

    public function getCreateAt()
    {
        return $this->createAt;
    }

    public function getCreateIp()
    {
        return $this->createIp;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getFileName()
    {
        return $this->filename;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getRepeatCount()
    {
        return $this->repeatCount;
    }

    public function getFileSize()
    {
        if (empty($this->fileSize)) {
            $this->fileSize = $this->formatSizeUnits($this->original['fileSize']);
        }

        return $this->fileSize;
    }

    public function markResolved()
    {
        return $this->changeStatus(Status::RESOLVED);
    }

    public function markUnresolved()
    {
        return $this->changeStatus(Status::UNRESOLVED);
    }

    public function markShelve()
    {
        return $this->changeStatus(Status::SHELVE);
    }

    private function changeStatus($status) {
        $tmp = explode('-', $this->content);
        $tmp[2] = $status;
        $content = join('-', $tmp);
        
        $file = fopen(File::SAVEPATH.'md5-count.txt', 'r+');
        fseek($file, $this->line * 89);
        fwrite($file, $content, 88);
        fclose($file);
        return;
    }

    private function formatSizeUnits($bytes, $index = 0)
    {
        $humanSizes = ['byte', 'KB', 'MB', 'GB'];

        if ($bytes < 1024) {
            return $index == 0 ? $bytes.' byte' : number_format($bytes, 2).' '.$humanSizes[$index];
        } else {
            return $this->formatSizeUnits(number_format($bytes / 1024, 2), ++$index);
        }
        
    }

    public static function createLog($md5) {
        $lines = file(File::SAVEPATH.'md5-count.txt');
        $flag = false;
        foreach ($lines as $line => $content) {
            if ($md5 === substr($content, 0, 32)) {
                $flag = true;
                break;
            }
        }
        if (!$flag) {
            throw new Exception('File same count not exists!');
        }
        
        return new self($content, $line);
    }

    public static function rm($md5) {
        $content = file_get_contents(File::SAVEPATH.'md5-count.txt');
        $content = preg_replace('/'.$md5.'.*\n/', '', $content);
        file_put_contents(File::SAVEPATH.'md5-count.txt', $content);

        File::rm($md5.'.txt');
    }
}

interface Renderable
{
    public function render();
}
abstract class MultiRenderable
{
    private $elements = [];

    public function addElement($element)
    {
        $this->elements[] = $element;
    }

    public function render()
    {
        $content = '<ul>';
        foreach ($this->elements as $element) {
            $content .= sprintf('%s', $element->render());
        }
        $content .= '</ul>';
        return $content;
    }
}

class ListItem
{
    private $elements = [];

    public function addElement($element)
    {
        $this->elements[] = $element;
    }

    public function render()
    {
        $content = '<li>';
        foreach ($this->elements as $element) {
            $content .= sprintf('%s', $element->render());
        }
        $content .= '</li>';
        return $content;
    }
}

class Button
{
    private $text;
    private $class;
    private $action;

    public function __construct($text, $action = '', $class = '')
    {
        $this->text = $text;
        $this->class = $class;
        $this->action = $action;
    }

    public function render()
    {
        $class =  $this->class?sprintf('class="%s"', $this->class): '';
        $action =  $this->action?sprintf("onclick=\"%s\"", $this->action): '';
        return sprintf('<button %s %s>%s</button>', $class, $action, $this->text);
    }
}

class Form
{
    private $elements = [];
    private $method;
    private $action;

    public function __construct($method = "POST", $action = '?')
    {
        $this->method = $method;
        $this->action = $action;
    }

    public function addElement($element)
    {
        $this->elements[] = $element;
    }

    public function render()
    {
        $content = sprintf(
            '<form method="%s" action="%s">',
            $this->method,
            $this->action
        );
        foreach ($this->elements as $element) {
            $content .= $element->render();
        }
        $content .= '</form>';
        return $content;
    }
}

class Link
{
    private $text;
    private $url;

    public function __construct($text, $url = '#')
    {
        $this->text = $text;
        $this->url = $url;
    }

    public function render()
    {
        return sprintf('<a target="_blank" download="" href="%s">%s</a>', $this->url, $this->text);
    }
}

class Table
{
    private $elements = [];

    public function addElement($element)
    {
        $this->elements[] = $element;
    }

    public function render()
    {
        $content = '<table>';
        foreach ($this->elements as $element) {
            $content .= $element->render();
        }
        $content .= '</table>';
        return $content;
    }
}

class TableHeader
{
    private $headers = [];

    public function __construct($headers)
    {
        $this->headers = $headers;
    }

    public function render()
    {
        $content = '<thead><tr>';
        foreach ($this->headers as $header) {
            $content .= sprintf('<th>%s</th>', $header);
        }
        $content .= '</tr></thead>';
        return $content;
    }
}

class TableBody
{
    private $bodies = [];

    public function addElement($element)
    {
        $this->bodies[] = $element;
    }

    public function render()
    {
        $content = '<tbody>';
        foreach ($this->bodies as $body) {
            $content .= $body->render();
        }
        $content .= '</tbody>';
        return $content;
    }
}

class TableBodyItem
{
    private $bodies = [];

    public function __construct($bodies)
    {
        $this->bodies = $bodies;
    }

    public function render()
    {
        $content = '<tr>';
        foreach ($this->bodies as $body) {
            $content .= sprintf('<td>%s</td>', $body->render());
        }
        $content .= '</tr>';
        return $content;
    }
}

class Font
{
    private $text;
    private $class;

    public function __construct($text, $class = '')
    {
        $this->text = $text;
        $this->class = $class;
    }

    public function render()
    {
        return sprintf('<label class="%s">%s</label>', $this->class, $this->text);
    }
}

class View
{
    private $elements = [];
    private $class;

    public function __construct($class='')
    {
        $this->class = $class;
    }

    public function addElement($element)
    {
        $this->elements[] = $element;
    }

    public function render()
    {
        $class = $this->class ? ' class="'.$this->class.'"': '';
        $content = '<div'.$class.'>';
        foreach ($this->elements as $element) {
            $content .= $element->render();
        }
        $content .= '</div>';
        return $content;
    }
}




if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $file = new File();
        $file->name('log')->move();
        exit('ReportSuccess');
    } catch (InvalidArgumentException $e) {
        exit($e->getMessage());
    }
} else {
    session_start();
    if(isset($_SESSION['user']) && $_SESSION['user']=='admin'){
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        switch ($action) {
            case 'logout': {
                unset($_SESSION['user']);
                header('Location:/');
                break;
            }
            case 'del': {
                Log::rm($_GET['filename']);
                header('Location:/');
                break;
            }
            case 'resolved': {
                $log = Log::createLog($_GET['filename']);
                $log->markResolved();
                header('Location:/');
                break;
            }
            case 'unresolved': {
                $log = Log::createLog($_GET['filename']);
                $log->markUnresolved();
                header('Location:/');
                break;
            }
            case 'shelve': {
                $log = Log::createLog($_GET['filename']);
                $log->markShelve();
                header('Location:/');
                break;
            }
            case 'read': {
                $filename = $_GET['filename'];
                $filename = str_replace(['/', '\\'], '', $filename);
                $file = File::SAVEPATH . $filename.'.txt';
                if (file_exists($file)) {
                    rendStyle();
                    $log = Log::createLog($filename);
                    $str = file_get_contents($file);

                    $dateAndTime = explode(' ', $log->getName());
                    $view = new View('float-right');

                    $view1 = new View('col');
                    $view1->addElement(new Font('上报时间:'));
                    $view1->addElement(new Font("<br/>{$dateAndTime[0]}"));
                    $view1->addElement(new Font(" {$dateAndTime[1]}"));

                    $view2 = new View('col');
                    $view2->addElement(new Font('IP:'));
                    $view2->addElement(new Font("[{$log->getCreateIp()}]"));

                    $view3 = new View('col');
                    $view3->addElement(new Font('重复次数:'));
                    $view3->addElement(new Font(" [ {$log->getRepeatCount()} ] "));

                    $view4 = new View('col');
                    $view4->addElement(new Font('文件大小:'));
                    $view4->addElement(new Font("<br/>{$log->getFileSize()}"));
                    $view4->addElement(new Button('删除', "window.open('?action=del&filename={$log->getFilename()}', '_self')", 'red'));

                    $view5 = new View('col');
                    $view5->addElement(new Font('当前状态: '));
                    $view5->addElement(new Font(Status::getText($log->getStatus()), Status::$colors[$log->getStatus()]));

					$view6 = new View('col');
					if($log->getStatus()!= Status::UNRESOLVED){$view6->addElement(new Button('未解决', "window.open('?action=unresolved&filename={$log->getFilename()}', '_self')"));}
					if($log->getStatus()!= Status::RESOLVED){$view6->addElement(new Button('已解决', "window.open('?action=resolved&filename={$log->getFilename()}', '_self')"));}
					if($log->getStatus()!= Status::SHELVE){$view6->addElement(new Button('搁置', "window.open('?action=shelve&filename={$log->getFilename()}', '_self')"));}
					
                    $view->addElement($view1);
                    $view->addElement($view2);
                    $view->addElement($view3);
                    $view->addElement($view4);
                    $view->addElement($view5);
                    $view->addElement($view6);
					
                    echo $view->render();
                    //$str = str_replace("\r\n", "<br />", $str);
                    echo "<pre style='font-size:17px;'>{$str}<pre>";
                    exit;
                } else {
                    header('HTTP/1.0 404 Not Found');
                    echo 'not found ' . $filename;
                    exit;
                }
                break;
            }
        }
    }else{
        if(isset($_GET['action']) && 'login'==$_GET['action'] && 'sml22'==$_GET['password']){
            $_SESSION['user']='admin';
            header("location:/");
        }else{
            exit("<!DOCTYPE html>
                  <html><body><form style='text-align:center;margin:0 auto;'>
                    <input type='password' name='password' value='sml' />
                    <input type='hidden' name='action' value='login' />
                    <input type='submit'>
                  </form></body></html>");
        }
    }
}
if (!File::installed()) {
    $j_path = File::SAVEPATH;
    $files = scandir($j_path);
    foreach($files as $key=>$value){
        if(strpos($value, '-') !== false && strlen($value)>20){
        (new File)->j_move($value); 
        }
    }
}

$logList = (new File())->getLogList();

$table = new Table();
$tableHeader = new TableHeader([
    '上报时间',
    'IP',
    '文件大小',
    '重复次数',
    '状态',
    '操作'
]);
$table->addElement($tableHeader);
$tableBody = new TableBody();
foreach ($logList as $log) {
    $view = new View();
    $url = '?action=read&filename=' . $log->getFilename();
    $view->addElement(new Button('查看', "window.open('$url')"));
	if($log->getStatus()!= Status::UNRESOLVED){$view->addElement(new Button('未解决', "window.open('?action=unresolved&filename={$log->getFilename()}', '_self')"));}
	if($log->getStatus()!= Status::RESOLVED){$view->addElement(new Button('已解决', "window.open('?action=resolved&filename={$log->getFilename()}', '_self')"));}
	if($log->getStatus()!= Status::SHELVE){$view->addElement(new Button('搁置', "window.open('?action=shelve&filename={$log->getFilename()}', '_self')"));}
    $view->addElement(new Button('删除', "window.open('?action=del&filename={$log->getFilename()}', '_self')", 'red'));

    $tableBody->addElement(new TableBodyItem([
        new Link($log->getName(), '/log/' . $log->getFilename().'.txt'),
        new Font($log->getCreateIp()),
        new Font($log->getFileSize()),
        new Font($log->getRepeatCount()),
        new Font(Status::getText($log->getStatus()), Status::$colors[$log->getStatus()]),
        $view
    ]));
}
$table->addElement($tableBody);
 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>日志列表</title>
    <?PHP rendStyle();?>
</head>
<body>
<h3>日志列表 - <a href="/?action=logout">[退出]</a></h3>
<?php
 echo $table->render();
?>
</body>
</html>
