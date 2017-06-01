<?php
  
class CRUD
{
   public $log;
   public $conn;
   public $returns = false;
   protected $keys;
   protected $fields;
   protected $tblname;
    

   /**
   * constructor
   * @args $tblname - the name of the table or join to be edited
   * Initiates logging and the databae connection
   */
   public function __construct($tblname)
   { 
        require_once 'Log.php';
        $this->log = Log::singleton('file','/var/log/reports/crud.log','CRUD','',PEAR_LOG_DEBUG);
        $this->log->notice('authenticated');
	if (DBMS == 'mysql')
	   $this->conn = new PDO(DBMS.':host='.HOST.
			      ';dbname='.DBNAME,
	                      DBUSER,DBPASS);
	elseif (DBMS == 'pgsql')
	{
	    $dsn = DBMS.':host='.HOST.
		   ';dbname='.DBNAME.
		   ';user='.DBUSER.
		   ';password='.DBPASS;
	   $this->log->debug('DSN: '.$dsn);
	   $this->conn = new PDO($dsn);
        }
	$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->tblname=$tblname;
	$this->setfields(); //TODO need option to supply fields
   }
   /*
    * Get the list of fields from the table
    */
   public function setfields()
   {
	try
	{
	        $this->log->debug("Setting fields");
		$table = preg_replace('/^.*?\./','',$this->tblname);
                $sql="SELECT column_name,data_type from information_schema.columns where table_name='{$table}'";
                $this->log->debug($sql);
		$result=$this->conn->query($sql);
		$this->fields=array();
		$this->data_types=array();
		foreach ($result as $field) 
		{
		   $this->log->debug("field: $field[0]");
		   $this->fields[]=$field[0]; 
		   $this->data_types[$field[0]]=$field[1]; 
		}
                $this->log->debug(implode("\t",$this->fields));
		$this->keys=array();
		$sql="SELECT column_name from information_schema.key_column_usage where table_name='{$this->tblname}'";
		$result=$this->conn->query($sql);
		foreach ($result as $field)
		{
		   $this->log->debug("key: $field[0]"); 
		   $this->keys[]=$field[0]; 
		}
	}
	catch(PDOException $err)
	{
	   $this->log->debug($err->getMessage());
	}
    }

    function tablelist($lines=10,$message='')
    {
      $format=isset($_REQUEST['format'])?$_REQUEST['format']:"html";
      $this->log->debug(implode("\t",$this->fields));
      $fields = !empty($_REQUEST['show'])?$_REQUEST['show']:$this->fields;
      $this->log->debug(implode("\t",$fields));
      $lines=isset($_REQUEST['lines'])?$_REQUEST['lines']:$lines;
      $this->offset=isset($_REQUEST['offset'])?$_REQUEST['offset']:0;
      if (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'next') $this->offset += $lines;
      $wherearr=array();
      foreach ($this->fields as $field)
      {
         //Text or char type fields
         if (!empty($_REQUEST["filter_$field"]) && preg_match('/char|text|blob/',$this->data_types[$field]) )
	 {
	     if (preg_match('/^"(.*)"/',$_REQUEST["filter_$field"],$match))
		     $wherearr[]="$field = '$match[1]'";
	  
	     else{	      	 
		     $words=preg_split('/\s+/',$_REQUEST["filter_$field"]);
		     foreach ($words as $word)
		          if (DBMS == 'pgsql' && preg_match('/^!~(.*)$/',$word,$match))
			  {
			  	$wherearr[]="($field !~* '{$match[1]}' or $field is NULL)";
				//break;
		          }
			  elseif (DBMS == 'mysql' && preg_match('/^!~(.*)$/',$word,$match))
			  {
			  	$wherearr[]="($field not rlike '{$match[1]}' or $field is NULL)";
				//break;
			  }
		          elseif (preg_match('/^!(.*)$/',$word,$match))
			  	$wherearr[]="($field not like '%{$match[1]}%' or $field is NULL)";
			  elseif (preg_match('/^\>(.*)$/',$word,$match))            
			 	$wherearr[]="$field > '{$match[1]}'"; 
			  elseif (preg_match('/^\<(.*)$/',$word,$match))            
			 	$wherearr[]="$field < '{$match[1]}'"; 
			  elseif (DBMS == 'pgsql' && preg_match('/^\~(.*)$/',$word,$match))            
			  {
			 	$wherearr[]="$field ~* '{$match[1]}'"; 
				//break;
			  }
			  elseif (DBMS == 'mysql' && preg_match('/^\~(.*)$/',$word,$match))            
			  {
			 	$wherearr[]="$field rlike '{$match[1]}'"; 
				//break;
			   }
			  else	
		     		$wherearr[]="$field like '%{$word}%'";
	     }
  	 }
         //Date and time fields
         elseif (!empty($_REQUEST["filter_$field"]) && preg_match('/date|time/',$this->data_types[$field]) )
	 {
	     if (preg_match('/^"(.*)"/',$_REQUEST["filter_$field"],$match))
		     $wherearr[]="$field = '$match[1]'";
	     else{	      	 
		     $words=preg_split('/\s+/',$_REQUEST["filter_$field"]);
		     foreach ($words as $word)
		          if (preg_match('/^!(.*)$/',$word,$match))
			  	$wherearr[]="($field <>  '{$match[1]}' or $field is null)";
			  elseif (preg_match('/^\>(.*)$/',$word,$match))            
			 	$wherearr[]="$field > '{$match[1]}'"; 
			  elseif (preg_match('/^\<(.*)$/',$word,$match))            
			 	$wherearr[]="$field < '{$match[1]}'"; 
			  else	
		     		$wherearr[]="$field = '{$word}'";
	     }
  	 }
         //Assume numeric otherwise
         elseif (!empty($_REQUEST["filter_$field"]) )
	 {
	    	      	 
	     $words=preg_split('/\s+/',$_REQUEST["filter_$field"]);
             $this->log->debug('filtering');
	     foreach ($words as $word)
             {
                  $this->log->debug($word);
	          if (preg_match('/^!(.*)$/',$word,$match))
		  	$wherearr[]="($field <>  {$match[1]}".((DBMS=='pgsql')?"::{$this->data_types[$field]}":"")." or $field is NULL)";
		  elseif (preg_match('/^\>(.*)$/',$word,$match))            
		 	$wherearr[]="$field > {$match[1]}".((DBMS=='pgsql')?"::{$this->data_types[$field]}":""); 
		  elseif (preg_match('/^\<(.*)$/',$word,$match))            
		 	$wherearr[]="$field < {$match[1]}".((DBMS=='pgsql')?"::{$this->data_types[$field]}":""); 
		  else	
	     		$wherearr[]=("$field  = $word").((DBMS=='pgsql')?"::{$this->data_types[$field]}":"");
	     }
  	 }
      }
      $where = implode(' and ',$wherearr);
      $sql = "select ".implode(',',$fields)." from $this->tblname";
      $sql .= ($where)?" where $where":'';
      if (isset($_REQUEST['sort']))
          $sql.=" order by ".str_replace('_',' ',$_REQUEST['sort']);
      $sql .= " limit $lines offset {$this->offset}";
      $this->log->debug($sql);
      $result = $this->conn->query($sql) or die("query '$sql' failed");
      
      $format.='_output';
      if ($this->returns) ob_start();
      $this->$format($fields,$result,$lines);
      if ($this->returns) return ob_get_contents();
   }
   function html_output($fields,$result,$lines)
   {

?>     
<html>
   <head>
      <link rel ="stylesheet" type="text/css" href="/reports/leads.css" />
   </head>
   <body>
   <div name="message" style="color:red">
     <?php if (!empty($this->message)) echo $this->message?>
    </div> 
	<form method="POST">
	<?php foreach ($this->keys as $k) : ?>
	   <input type="hidden" name="original_<?php echo $k?>" value="<?php echo '$row[$k]'?>">
	<?php endforeach ?>

	   <table>
	      <tr>
                  <th>Action</th>
	      <?php
     		foreach ($fields as $field): 
     
        	   $sort_checked=(isset($_REQUEST['sort']) && $_REQUEST['sort']==$field)?"checked":"";
        	   $sort_checked_desc=(isset($_REQUEST['sort']) && $_REQUEST['sort']==$field."_desc")?"checked":"";
                   $show_checked=(isset($_REQUEST['show']) && in_array($field,$_REQUEST['show']))?"checked":"";
              ?>  
                  <th>
                    <table>
                       <tr>
                           <td> <?php echo $field?></td>
                           <td> 
                           <input type="radio" name="sort" value="<?php echo $field ?>" 
                                  <?php echo $sort_checked?> 
                           >
                                   <input type="checkbox"  name="show[]" value="<?php echo $field ?>" 
                                  <?php echo $show_checked?>
                           >
                             <br>
                           <input type="radio" name="sort" value="<?php echo $field ?>_desc" 
                                  <?php echo $sort_checked_desc?> 
                           >
                           </td>
                         </tr>
                       </table>
                  </th>
     	       <?php endforeach ?>
	      </tr>
              <!-- filter row -->
              <tr>
                 <td><input type="submit" value="filter"></td>
                <?php foreach ($fields as $field):?> 
                  <td class="filter"><input name="<?php echo "filter_$field" ?>"
                      <?php if (!empty($_REQUEST["filter_$field"])):?>
                           value="<?php echo $_REQUEST["filter_$field"]?>"
                      <?php endif ?>
                      >
		  </td>
		<?php endforeach ?>  
	     </tr>

 
      <?php while ($row = $result->fetch()):?>
            <tr>
                 <td class="control"><a href="<?php echo $_SERVER['REQUEST_URI']?>?action=modify<?php foreach ($this->keys as $k) echo "&original_$k={$row[$k]}";
	      echo '">Modify</a> | '.
		   '<a href ="'.$_SERVER['REQUEST_URI'].
		   '?action=delete_confirm';
	      foreach ($this->keys as $k)
	           echo "&original_$k={$row[$k]}";	   
	      echo '">Delete</a></td>'?> 
	     <?php foreach ($fields as $field) :?>
                 <td><div class="cell"><div class="text"><?php echo $row[$field] ?></div></div></td>
             <?php endforeach?>
             </tr>
      <?php endwhile?>
             <tr>
	         <td colspan="<?php echo (count($fields)+1)?>" class="control" id="bottom">
		      <a href="<?php echo $_SERVER['REQUEST_URI']?>?action=create">Add New</a> : 
		      Show <input size="3" name="lines" value="<?php echo $lines?>">  
                      lines<input type="submit" value="filter">
                      <input type="submit" name='action' value="next">
		      <input type="hidden" name="offset"  value="<?php echo $this->offset?>">
                      <input type="submit" name="format" value="csv">
		 </td>
	      </tr>	 
           <table>
    </form>
    <?php
  } #function html_output


  function csv_output($fields,$result,$lines)
  {

	header('content-type:text/csv'); 
	header('content-disposition:attatchmen;filename="'.$this->tblname.'.csv"'); 
        array_walk($fields,"trim");
	echo '"'.implode('","',$fields)."\"\r\n";
        while ($row = $result->fetch(PDO::FETCH_ASSOC))
	{	
	      array_walk($row,"trim");
	      echo '"'.implode('","',$row)."\"\r\n";
	}
  } #function csv_output


  function csv2_output($fields,$result,$lines)
  {

	header('content-type:text/csv'); 
	header('content-disposition:attatchmen;filename="'.$this->tblname.'.csv"'); 
        array_walk($fields,"trim");
	echo '"'.implode('","',$fields)."\"\r\n";
        while ($row = $result->fetch(PDO::FETCH_ASSOC))
	{	
              foreach($row as $key=>$val) 
              {
                   $row[$key]=strip_tags($row[$key]);
                   $row[$key]=trim($row[$key]);
              }
	      $row=preg_replace('/\r\n/',' ',$row);
	      $row=preg_replace('/\n/',' ',$row);
	      echo '"'.implode('","',$row)."\"\r\n";
	}
  } #function csv_output

   function getWhere()
   {
      $sqlarr=array();
      foreach ($this->keys as $k)
             $sqlarr[]="$k='".$_REQUEST["original_$k"]."'";
      return implode(' and ',$sqlarr);
   }
   /*
    * Dislays a form that can be used for updating and creating new rows
    *
    */
   function modify_create_form($action)
   { 
        $this->log->debug("modify_create: ".$action);
        //if the keys were passed we know that an existing row is being modified
        $allkeys=true;
        foreach ($this->keys as $k)
        {
           if (!isset($_REQUEST["original_$k"])) $allkeys=false;
        }  
        if ($allkeys)
        {
            $sql = "select * from $this->tblname where ";
	        $sql .= $this->getWhere();
	        $this->log->debug($sql);
            $result = $this->conn->query($sql) or die("query '$sql' failed");
            $row=$result->fetch();
	    if ($action == "delete_confirm")
	    { 
            $newaction="delete"; 
		    echo "Are you sure?";
	    }else $newaction = "save";
        }else 
        {
            $row = array();
            foreach ($this->fields as $field)
               $row[$field]='';
	        $newaction = "add";
        }	  
?>
<style type="text/css">
   table {background-color:blue;border-radius:10px;box-shadow:10px 10px 5px gray}
   td {background-color:lightblue;padding-left:15px;padding-right:15px;border-radius:10px}
   th {color:white}
</style>
<form  method="POST">
<?php foreach ($this->keys as $k) : ?>
<input type="hidden" name="original_<?php echo $k?>" value="<?php echo $row[$k]?>">
<?php endforeach ?>
<table>
   <tr><th colspan="2"><?php echo ucfirst($action) ?></th></tr>
   <tr>
      <?php foreach($this->fields as $field): ?>
      <td><?php echo $field?></td>
	  <td><input name="<?php echo $field?>" value="<?php echo $row[$field] ?>"  
	  <?php echo ($newaction=='delete')?'disabled':''?>></td>
   </tr>
      <?php endforeach ?>
   <tr>  
      <td colspan="2">
	    <input type="submit" name="action" value="<?php echo $newaction?>">
	  </td>
    </tr>		 
</table>
</form>
<?php 
   }   

   function update()
   {
     $whereArray=array();
     foreach($this->keys as $k)
     {
         if (isset($_REQUEST["original_$k"]))
             array_push($whereArray,$k.' = '.$_REQUEST["original_$k"]);
     }
     $setArray =array();
     foreach($this->fields as $field)
     {
         if (!empty($_REQUEST[$field]))
             $setArray[] = "$field = '{$_REQUEST[$field]}'";
     }
         $sql = "update ".$this->tblname. 
                 ' set '.implode(',',$setArray)."\n". 
		 'where '.implode("\nAND ",$whereArray);

         $this->log->debug($sql);
         $this->conn->query($sql) or die("query '$sql' failed");
         $this->message = "table $this->tblname updated <br>";
         $this->tablelist();
   }

   function add()
   {
       $insertFieldArray=array();
       $insertValueArray=array();
       foreach($this->fields as $field)
       {
         if (isset($_REQUEST[$field]))
             $insertFieldArray[]=$field;
             $insertValueArray[]=$_REQUEST[$field];
       }
       $fieldArray=array();
       foreach($this->fields as $field)
       {    
            if (!empty($_REQUEST[$field]))
	    {
                $fieldArray[] = $field;
                $valueArray[] = $_REQUEST[$field];
            }
       }
       $sql = "insert into  $this->tblname(".
       implode(',',$fieldArray).") values ('".
       implode("','",$valueArray)."')";
       $this->log->debug("INSERT: ".$sql);
       $this->conn->query($sql) or die("query '$sql' failed");
       $this->message = "new row added to table $this->tblname<br>";
       $this->tablelist();
   }

   function delete()
   {
	$sql = "delete from ".$this->tblname. 
        " where ";
        $sql .= $this->getWhere();
	$this->conn->query($sql) or die("query '$sql' failed");
	$this->message = "row from table $this->tblname deleted <br>$sql";
    $this->tablelist();
   }
}
?>
