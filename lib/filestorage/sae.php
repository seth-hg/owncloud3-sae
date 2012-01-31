<?php
/**
 * storage backend for SAE.
 */
class OC_Filestorage_SAE extends OC_Filestorage{
	private $saeStor;
	private $domain;
	private static $mimetypes=null;
	public function __construct($arguments){
		$this->saeStor = new SaeStorage();
		$this->domain=$arguments['domain'];
		//$this->domain = "cloud";
	}
	/* no SAE api for creating directory? */
	public function mkdir($path){
		return false;
	}
	/* should return false if dir not empty */
	public function rmdir($path){
		if($return=$this->saeStor->deleteFolder($this->domain, $path)){
			$this->clearFolderSizeCache($path);
		}
		return $return;
	}
	/* need to return a dir handle */
	public function opendir($path) {
		$dirs = array();
		$num = 0;
		while ($ret=$this->saeStor->getList($this->domain, "*", 100, $num)) {
			$num = $num + count($ret);
			$dirs = array_merge($dirs, $ret);
		}

		$id=uniqid();
		global $FAKEDIRS;
		$FAKEDIRS[$id]=$dirs;
		return opendir("fakedir://$id");
	}
	/* FIXME: no directory support yet */
	public function is_dir($path){
		//return (is_dir($this->datadir.$path) or substr($path,-1)=='/');
		if ($path == '' or $path == '/')
			return true;
		return false;
	}
	public function is_file($path){
		return true;
	}
	public function filetype($path){
		/*
		$filetype=filetype($this->datadir.$path);
		if($filetype=='link'){
			$filetype=filetype(readlink($this->datadir.$path));
		}
		return $filetype;
		*/
		return "file";
	}
	public function is_readable($path){
		return true;
	}
	public function is_writeable($path){
		return true;
	}
	public function file_exists($path){
		return $this->saeStor->fileExists($this->domain, $path);
	}
	/* TODO: SAE file has only 3 attributes: fileName, length & datetime */
	public function stat($path){
        	$attr = $this->saeStor->getAttr($this->domain, $path);
                $ret = array("size"=>$attr["length"], "mtime"=>$attr["datetime"]);
		return $ret;
	}
	public function filesize($path){
		if($this->is_dir($path)){
			return $this->getFolderSize($path);
		}else{
			//return filesize($this->datadir.$path);
			$attrs = $this->saeStor->getAttr($this->domain, $path, array('length'));
			return $attrs['length'];
		}
	}
	/* TODO: how to get the following attributes? */
	public function filectime($path){
        	$attr = $this->saeStor->getAttr($this->domain, $path);
		return $attr["datetime"];
		//return filectime($this->datadir.$path);
	}
	public function filemtime($path){
        	$attr = $this->saeStor->getAttr($this->domain, $path);
		return $attr["datetime"];
		//return filemtime($this->datadir.$path);
	}
	public function fileatime($path){
        	$attr = $this->saeStor->getAttr($this->domain, $path);
		return $attr["datetime"];
		//return fileatime($this->datadir.$path);
	}
	public function readfile($path){
          	$data = $this->saeStor->read($this->domain, $path);
                echo $data;
	}
	public function file_get_contents($path) {
		return $this->saeStor->read($this->domain, $path);
	}
	public function file_put_contents($path,$data){
		if($return=$this->saeStor->write($this->domain, $path, $data)){
			$this->clearFolderSizeCache($path);
		}
	}
	public function unlink($path){
		//$return=$this->saeStor->delete($this->domain, $path);
		$return=$this->delTree($path);
		$this->clearFolderSizeCache($path);
		return $return;
	}
	/* TODO: rename not supported by SAE */
	public function rename($path1,$path2){
		$src = "saestor://".$this->domain.$path1;
		$dst = "saestor://".$this->domain.$path2;
		return rename($src, $dst);
	}
	public function copy($path1,$path2){
		$src = "saestor://".$this->domain.$path1;
		$dst = "saestor://".$this->domain.$path2;
		return copy($src, $dst);
	}
	public function fopen($path,$mode){
		$p = "saestor://".$this->domain.$path;
		if($return=fopen($p, $mode)){
			switch($mode){
				case 'r':
					break;
				case 'r+':
				case 'w+':
				case 'x+':
				case 'a+':
					$this->clearFolderSizeCache($path);
					break;
				case 'w':
				case 'x':
				case 'a':
					$this->clearFolderSizeCache($path);
					break;
			}
		}
		return $return;
	}

	public function getMimeType($fspath){
		if($this->is_readable($fspath)){
			$mimeType='application/octet-stream';
			if ($mimeType=='application/octet-stream') {
				self::$mimetypes = include('mimetypes.fixlist.php');
				$extention=strtolower(strrchr(basename($fspath), "."));
				$extention=substr($extention,1);//remove leading .
				$mimeType=(isset(self::$mimetypes[$extention]))?self::$mimetypes[$extention]:'application/octet-stream';
				
			}
			if ($this->is_dir($fspath)) {
				// directories are easy
				return "httpd/unix-directory";
			}
			if($mimeType=='application/octet-stream' and function_exists('finfo_open') and function_exists('finfo_file') and $finfo=finfo_open(FILEINFO_MIME)){
				$mimeType =strtolower(finfo_file($finfo,$this->datadir.$fspath));
				$mimeType=substr($mimeType,0,strpos($mimeType,';'));
				finfo_close($finfo);
			}
			if ($mimeType=='application/octet-stream' && function_exists("mime_content_type")) {
				// use mime magic extension if available
				$mimeType = mime_content_type($this->datadir.$fspath);
			}
			if ($mimeType=='application/octet-stream' && OC_Helper::canExecute("file")) {
				// it looks like we have a 'file' command,
				// lets see it it does have mime support
				$fspath=str_replace("'","\'",$fspath);
				$fp = popen("file -i -b '{$this->datadir}$fspath' 2>/dev/null", "r");
				$reply = fgets($fp);
				pclose($fp);

				//trim the character set from the end of the response
				$mimeType=substr($reply,0,strrpos($reply,' '));
			}
			if ($mimeType=='application/octet-stream') {
				// Fallback solution: (try to guess the type by the file extension
				//if(!self::$mimetypes){
				if(!self::$mimetypes || self::$mimetypes != include('mimetypes.list.php')){
					self::$mimetypes=include('mimetypes.list.php');
				}
				$extention=strtolower(strrchr(basename($fspath), "."));
				$extention=substr($extention,1);//remove leading .
				$mimeType=(isset(self::$mimetypes[$extention]))?self::$mimetypes[$extention]:'application/octet-stream';
			}
			return $mimeType;
		}
	}

	public function toTmpFile($path){
		//$tmpFolder=sys_get_temp_dir();
		$tmpFolder=SAE_TMP_PATH;
		$filename=tempnam($tmpFolder,'OC_TEMP_FILE_'.substr($path,strrpos($path,'.')));
		$fileStats = $this->stat($path);
		$fileData = $this->read($path);
		if (!$fileData)
			return false;
		file_put_contents($tmpFolder, $filename, $fileData);
		//touch($filename, $fileStats['mtime'], $fileStats['atime']);
		return $filename;
	}

	/* upload temp file to SAE storage */
	public function fromTmpFile($tmpFile,$path){
		//$filestats = stat($tmpfile);
		return $this->saeStor->upload($this->domain, $path, $tmpFile);
		/*
		$filestats = stat($tmpfile);
		if(rename($tmpFile,$this->datadir.$path)){
			touch($this->datadir.$path, $fileStats['mtime'], $fileStats['atime']);
			$this->clearFolderSizeCache($path);
			return true;
		}else{
			return false;
		}
		*/
	}

	/* where is uploaded file in SAE? */
	public function fromUploadedFile($tmpFile,$path){
          	if ($this->saeStor->upload($this->domain, $path, $tmpFile)) {
                	$this->clearFolderSizeCache($path);
                        return true;
          	} else {
                	return false;
                }
	}

	private function delTree($dir) {
		if (!$this->file_exists($dir)) return true;
		/* delete a single file */
		if (!$this->is_dir($dir))
			return $this->saeStor->delete($this->domain, $dir);
		/* delete a directory */
		$return  = $this->saeStor->deleteFolder($this->domain, $dir);
		if ($return) {
			$this->clearFolderSizeCache($dir);
		}
		return $return;
	}

	public function hash($type,$path,$raw){
		return hash_file($type,$this->domain.$path,$raw);
	}

	public function free_space($path){
		$total = 2 * 1024 * 1024 * 1024;
		$used = $this->saeStor->getDomainCapacity($this->domain);
		return $total - $used;
	}

	public function search($query){
		return $this->searchInDir($query);
	}

	public function getLocalFile($path){
          	return "saestor://".$this->domain.$path;
	}

	private function searchInDir($query,$dir=''){
		$files=array();
		foreach ($this->saeStor->getListByPath($this->domain, $dir) as $item) {
			if ($item == '.' || $item == '..') continue;
			if(strstr(strtolower($item),strtolower($query))!==false){
				$files[]=$dir.'/'.$item;
			}
			if($this->is_dir($dir.'/'.$item)){
				$files=array_merge($files,$this->searchInDir($query,$dir.'/'.$item));
			}
		}
		return $files;
	}

	/**
	 * @brief get the size of folder and it's content
	 * @param string $path file path
	 * @return int size of folder and it's content
	 */
	public function getFolderSize($path){
		$path=str_replace('//','/',$path);
		if($this->is_dir($path) and substr($path,-1)!='/'){
			$path.='/';
		}
		$query=OC_DB::prepare("SELECT size FROM *PREFIX*foldersize WHERE path=?");
		$size=$query->execute(array($path))->fetchAll();
		if(count($size)>0){// we already the size, just return it
			return $size[0]['size'];
		}else{//the size of the folder isn't know, calulate it
			return $this->calculateFolderSize($path);
		}
	}

	/**
	 * @brief calulate the size of folder and it's content and cache it
	 * @param string $path file path
	 * @return int size of folder and it's content
	 */
	public function calculateFolderSize($path){
		if($this->is_file($path)){
			$path=dirname($path);
		}
		$path=str_replace('//','/',$path);
		if($this->is_dir($path) and substr($path,-1)!='/'){
			$path.='/';
		}
		$size=0;
		foreach ($this->saeStor->getListByPath($this->domain, $path) as $item) {
			if ($item == '.' || $item == '..') continue;
			$subFile = $path.'/'.$item;
			if ($this->is_file($subFile)) {
				$size += $this->filesize($subFile);
			} else {
				$size+=$this->getFolderSize($subFile);
			}
			/* cache it */
			if ($size > 0) {
				$query=oc_db::prepare("insert into *prefix*foldersize values(?,?)");
				$result=$query->execute(array($path,$size));
			}
		}
		/*
		if ($dh = $this->opendir($path)) {
			while (($filename = readdir($dh)) !== false) {
				if($filename!='.' and $filename!='..'){
					$subFile=$path.'/'.$filename;
					if($this->is_file($subFile)){
						$size+=$this->filesize($subFile);
					}else{
						$size+=$this->getFolderSize($subFile);
					}
				}
			}
			if($size>0){
				$query=oc_db::prepare("insert into *prefix*foldersize values(?,?)");
				$result=$query->execute(array($path,$size));
			}
		}
		*/
		return $size;
	}

	/**
	 * @brief clear the folder size cache of folders containing a file
	 * @param string $path
	 */
	public function clearFolderSizeCache($path){
		if($this->is_file($path)){
			$path=dirname($path);
		}
		$path=str_replace('//','/',$path);
		if($this->is_dir($path) and substr($path,-1)!='/'){
			$path.='/';
		}
		$query=OC_DB::prepare("DELETE FROM *PREFIX*foldersize WHERE path = ?");
		$result=$query->execute(array($path));
		if($path!='/' and $path!=''){
			$parts=explode('/',$path);
			//pop empty part
			$part=array_pop($parts);
			if(empty($part)){
				array_pop($parts);
			}
			$parent=implode('/',$parts);
			$this->clearFolderSizeCache($parent);
		}
	}
}
