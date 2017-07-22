<?php
/*
###### Graph Database Abstraction Layer ######

This module will allow keeping Graph data in a MySQL database
*/

require __DIR__ . '/vendor/autoload.php';
use Hashids\Hashids;

date_default_timezone_set('Asia/Kolkata');

$GLOBALS['HASHID_SALT'] = "MaceHub is the best";




/**
* A class that represents a node
*/
class Node
{

	// For reloading all the properties and labels from the
	// grpah incase the contents of the database is updated
	public function reload() 
	{
		$conn = $this->conn;
		// Clearing out data from the array so that it doesn't messes up with data
		$this->labels= [];
		$this->properties = array();

		// Finding internal ID of the node
		$id = mysqli_real_escape_string($conn,$this->ID);
		$sql1 = 'SELECT id FROM graphdb_entities WHERE name="'.$this->ID.'";';
		$result = $conn->query($sql1);
		$this->internalID = $result->fetch_assoc()['id'];

		// Loading labels
		$sql2 = 'SELECT prop_value from graphdb_properties WHERE prop_type="l" AND e_id="'.$this->internalID.'";';
		$result = $conn->query($sql2);
		$this->labels = [];
		while ($data = $result->fetch_assoc()) {
			array_push($this->labels, $data['prop_value']);
		}

		// Loading properties
		$sql3 = 'SELECT prop_name,prop_value FROM graphdb_properties WHERE prop_type="p" AND e_id="'.$this->internalID.'";';
		$result = $conn->query($sql3);
		$this->properties = array();
		while ($data = $result->fetch_assoc()) {
			$this->properties[$data['prop_name']] = $data['prop_value'];
		}
	}

	function __construct($conn,$id,$load=True) 
	{
		$this->conn = $conn;
		$this->ID = $id;
		$this->internalID = "";
		$this->labels = [];
		$this->properties = array();

		// Load the properties of the Node from db
		if ($load==True) {
			$this->reload();
		}

	}



	public function setProperty($key,$value) 
	{
		$conn = $this->conn;
		
		// Updating instance variable
		$this->properties[$key] = $value;

		// Updating database
		$key = mysqli_real_escape_string($conn,$key);
		$value = mysqli_real_escape_string($conn,$value);
		$time = date('Y-m-d H:i:s', time());
		$sql = 'INSERT INTO graphdb_properties(e_id,prop_name,prop_value,prop_type,modified_on) VALUES('.$this->internalID.',"'.$key.'","'.$value.'","p","'.$time.'");';
		if($conn->query($sql)===True) {
			return "ok";
		}
		else {
			return "Error in setting the entity for property : ".$conn->error;
		}

	}



	public function setLabel($label)
	{
		$conn = $this->conn;

		// Updating instance variable
		array_push($this->labels, $label);

		// Updating database
		$label = mysqli_real_escape_string($conn,$label);
		$time = date('Y-m-d H:i:s', time());
		$sql = 'INSERT INTO graphdb_properties(e_id,prop_name,prop_value,prop_type,modified_on) VALUES('.$this->internalID.',"","'.$label.'","l","'.$time.'");';
		if($conn->query($sql)===True) {
			return "ok";
		}
		else {
			return "Error in setting label for entity : ".$conn->error;
		}
	}



	public function getProperty($key)
	{
		return $this->properties[$key];
	}


	public function getLabel()
	{
		return $this->labels;
	}



	public function connect($node)
	{
		$conn = $this->conn;
		$a = new Relationship($conn,$this->internalID,$node->internalID);
		return $a;
	}
}





/**
* Relationship class
*/
class Relationship
{

	function reload() 
	{
		$conn = $this->conn;

		// Re setting all values of instance variables
		$this->labels = [];
		$this->properties = array();


		// Loading properties
		$e1_id = (int)$this->nodeID1;
		$e2_id = (int)$this->nodeID2;
		$sql = 'SELECT rel_name,rel_value FROM graphdb_relations WHERE rel_type="p" AND e1_id='.$e1_id.' AND e2_id='.$e2_id.';';
		$result = $conn->query($sql);
		while ($data = $result->fetch_assoc()) {
			$this->properties[$data['rel_name']] = $data['rel_value'];
		}
		

		// Loading labels
		$sql2 = 'SELECT rel_value from graphdb_relations WHERE rel_type="l" AND e1_id='.$e1_id.' AND e2_id='.$e2_id.';';
		$result = $conn->query($sql2);
		$this->labels = [];
		while ($data = $result->fetch_assoc()) {
			array_push($this->labels, $data['rel_value']);
		}


	}
	
	function __construct($conn,$node1,$node2,$load=True)
	{
		// All the instance variables
		$this->conn = $conn;
		$this->nodeID1 = "";
		$this->nodeID2 = "";
		$this->labels = [];
		$this->properties = array();
		$this->modifiedOn = "";

		$this->nodeID1 = $node1->internalID;
		$this->nodeID2 = $node2->internalID;

		if($load==True) {
			$this->reload();
		}

	}

	function setProperty($key,$value) 
	{
		$conn = $this->conn;
		$key = mysqli_real_escape_string($conn,$key);
		$value = mysqli_real_escape_string($conn,$value);
		$time = date('Y-m-d H:i:s', time());
		$sql = 'INSERT INTO graphdb_relations(e1_id,e2_id,rel_name,rel_value,rel_type,modified_on) VALUES('.$this->nodeID1.','.$this->nodeID2.',"'.$key.'","'.$value.'","p","'.$time.'");';
		if($conn->query($sql)) 
		{
			$this->properties[$key] = $value;
			return "ok";
		}
		else {
			return "Error in setting entity relation label : ".$conn->error;
		}

	}

	function setLabel($label) 
	{

		$conn = $this->conn;
		$label = mysqli_real_escape_string($conn,$label);
		$time = date('Y-m-d H:i:s', time());
		$sql = 'INSERT INTO graphdb_relations(e1_id,e2_id,rel_name,rel_value,rel_type,modified_on) VALUES('.$this->nodeID1.','.$this->nodeID2.',"","'.$label.'","l","'.$time.'");';
		if($conn->query($sql)) {
			array_push($this->labels, $label);
			return "ok";
		}
		else {
			return "Error in setting entity relation label : ".$conn->error;
		}

	}

	function getProperty($key) 
	{
		return $this->properties[$key];
	}

	function getLabels() 
	{
		return $this->labels;
	}

}






/**
* Main class
*/
class GraphDB 
{


	/*
	* Private method that deals with the creation of tables
	* in a mysql database
	*/
	private function setupTables($conn) 
	{
		$graphdb_entities = false;
		$graphdb_relations = false;
		$graphdb_properties = false;

		$sql1 = "CREATE TABLE IF NOT EXISTS graphdb_entities(id INT(6) AUTO_INCREMENT PRIMARY KEY, name TEXT NOT NULL);";

		$sql2 = "CREATE TABLE IF NOT EXISTS graphdb_relations(id INT(6) AUTO_INCREMENT PRIMARY KEY, e1_id INT NOT NULL,e2_id INT NOT NULL,rel_name TEXT,rel_value TEXT,rel_type VARCHAR(255), modified_on TIMESTAMP);";

		$sql3 = "CREATE TABLE IF NOT EXISTS graphdb_properties(id INT(6) AUTO_INCREMENT PRIMARY KEY,e_id INT NOT NULL,prop_name TEXT,prop_value TEXT,prop_type VARCHAR(255) NOT NULL,modified_on TIMESTAMP);";

		$errors = array(
			'graphdb_entities' => "",
			'graphdb_relations' => "",
			'graphdb_properties' => ""
			 );

		if ($conn->query($sql1)===True) {
			$graphdb_entities = true;
		}
		else {
			$errors['graphdb_entities'] = $conn->error;
		}

		if ($conn->query($sql2)===True) {
			$graphdb_relations = true;
		}
		else {
			$errors['graphdb_relations'] = $conn->error;
		}

		if ($conn->query($sql3)===True) {
			$graphdb_properties = true;
			return "ok";
		}
		else {
			$errors['graphdb_properties'] = $conn->error;
		}

		if($graphdb_entities && $graphdb_relations && $graphdb_properties === True) {
			return "ok";
		}
		else {
			return $errors;
		}
	}


	/*
	* Constructor method
	*/
	function __construct($conn) 
	{
		$this->conn = $conn;
		$this->setupTables($conn);
		
		// Counting no. of nodes
		$sql = 'SELECT * FROM graphdb_entities;';
		$result = $this->conn->query($sql);
		$this->numOfNodes = intval($result->num_rows);
	}

	

	/*
	Creates new entity with the given name
	*/
	public function createNode() 
	{
		$conn = $this->conn;
		$this->numOfNodes = $this->numOfNodes + 1;
		// Generating the node's hashid
		$hashids = new Hashids($GLOBALS['HASHID_SALT'], 7);	// 7 characters give us the power to express 3521.61 billion entities uniquely.
		$hashid = $hashids->encode($this->numOfNodes);

		$hashid = mysqli_real_escape_string($conn,$hashid);
		$sql = 'INSERT INTO graphdb_entities(name) VALUES("'.$hashid.'");';
		if($conn->query($sql)===True){
			$a = new Node($conn, $hashid);
			return $a;
		}
		else {
			return "Error in creating new entity : " . $conn->error;
		}
	}


	public function GQL()
	{
		$a = new GQL();
		return $a;
	}
}





/**
* For searching the graph database
*/
class GQL
{

	function __construct($conn)
	{
		$conn = $this->conn;
	}


	function node()
	{
		$conn = $this->conn;
		$a = new Node($conn)
	}


	function relationship() 
	{
		$conn = $this->conn;
		$a = new Relationship();

	}


	function search()
	{
		// Do the searching here
	}
}





?>