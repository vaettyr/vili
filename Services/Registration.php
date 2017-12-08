<?php 
	include_once("../Endpoint.php");
	include_once("../Table.php");
	
	$service = new Endpoint();
	
	$service->register(new Handler("POST", function () {
		$entity_config = json_decode(file_get_contents("./Tables/FilingEntity.json"), true);
		$filingentity = new Table($entity_config);
		$agent_config = json_decode(file_get_contents("./Tables/FilingAgent.json"), true);
		$filingagent = new Table($agent_config);
		
		$data = json_decode(file_get_contents('php://input'),TRUE);
		$officers = $data["Officers"];		
		
		//retrieve the committee type so that we have data about it available
		$committeetype_config = json_decode(file_get_contents("./Tables/CommitteeType.json"), true);
		$committeetype = new Table($committeetype_config);
		$type = $committeetype->read($query = new Query(['ID' => ['=' => $data['CommitteeType']]]))[0];
		
		//CreateAccount
		//Public
		$data['Status'] = $type['InitialStatus'];
		$data['CreateAccount'] = $type['CreateAccount'];
		//maybe do the same with the officer type table

		
		$conn = $filingentity->transaction("open");
		$filingagent->transaction('set', $conn);
		
		
		
		//maybe automatically set the status?
		$entity = $filingentity->create($data);

		$agents = [];
		foreach($officers as $officer) {
			$officer['FilingEntity'] = $entity['ID'];
			$officer['FilingEntity_VERSION'] = $entity['VERSION'];
			$agents[] = $filingagent->create($officer);
		}
		$filingentity->transaction("commit");
		
		$entity['Officers'] = $agents;
		$response = new Response(200, $entity);
		$response->send();
	}));
	
	$service->go();
?>