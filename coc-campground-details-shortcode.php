<?php

/*
Plugin Name: Camp Out Colorado Campground Details + Stocking Report
Plugin URI: https://www.campoutcolorado.com/
Description: Adds campground details to reviews. Use [coc_campdetails contract="{contract abrev}" parkid="{facilityID}"]. To output just the reservation button link. Add button="yes" as third attribute. 
Version: 1.3
Author: Troy Whitney
Author URI: https://www.campoutcolorado.com/
*/

//Register Shortcode: [coc_cg_details]
add_shortcode( 'coc_campdetails', 'coc_cg_details' );

//callback function for the shortcode. 
function coc_cg_details( $atts = NULL ) { 
	if ( !empty( $atts ) ) {
		global $wpdb;
		
		//contractCode
		$cCode = $atts['contract'];
		
		//Park ID
		$pID = $atts['parkid'];
		
		//code for just button
		$jButton = $atts['button'];
		
		//get coc camping data 
		$sql = "
		Select
		  coc_campground_details.idcoc_campground_details,
		  coc_campground_details.coc_campground_name,
		  coc_campground_details.elevation,
		  coc_campground_details.campsites,
		  coc_campground_details.season,
		  coc_campground_details.lat,
		  coc_campground_details.lon,
		  coc_campground_details.driving_directions,
		  coc_campground_details.amenities,
		  coc_campground_details.reserve_url,
		  coc_campground_details.res,
		  coc_campground_details.camp_updated,
		  coc_camp_regions.coc_camp_region_name,
		  coc_camp_regions.coc_camp_regions_lat,
		  coc_camp_regions.coc_camp_regions_long,
		  coc_camp_regions.map_url,
		  coc_forestry.coc_forestry_name,
		  coc_forestry.coc_forestry_url
		From
		  coc_camp_regions Inner Join
		  coc_campground_details
			On coc_camp_regions.idcoc_camp_regions =
			coc_campground_details.region Inner Join
		  coc_forestry
			On coc_forestry.idcoc_forestry =
			coc_campground_details.forestry
		Where
		  coc_campground_details.contract_id = '$cCode' And
		  coc_campground_details.facilityID = $pID";
		$cocResults = $wpdb->get_row( $sql );

		//checks when last the data was updated to determine if an API call needs to be made
		if ( strtotime( '-1 day' ) > strtotime( $cocResults->camp_updated ) ) {
			//if longer than 1 day since update, update database fields
			
			//my api
			$activeAPI = ''; //add api code here
			$activeURL = 'http://api.amp.active.com/camping/campground/details?contractCode='.$cCode.'&parkId='.$pID.'&api_key='.$activeAPI; 
			
			//API Call - Only call IF NOT updated within 1 day.
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $activeURL );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
			$result = curl_exec( $ch );
			
			curl_close( $ch );

			//parse XML to chop
			$xml = simplexml_load_string($result);
		
			//don't update if no connection/loss of data
			if  (!empty($xml)) {
				//have data, check update for specific fields
				$campTable = 'coc_campground_details';
				$nowDate = date( "Y-m-d H:i:s" );
				
				//Driving directions Update
				if ( !empty( $xml['drivingDirection'] ) && strlen( $xml['drivingDirection'] ) > 10){
					$wpdb->update(
								$campTable, 
								array (
									'driving_directions' => $xml['drivingDirection'],
									'camp_updated' => $nowDate
									),
								array (
									'idcoc_campground_details' => $cocResults->idcoc_campground_details
									),
								array (
									'%s',
									'%s'
									)
								);
				}
				
				if ( !empty( $xml->amenity ) ){
					foreach( $xml->amenity as $AMN ){
						$amArray[] = ( string )$AMN['name'];
						$amList = implode( ", ",$amArray );
					}
					//insert Amenity List if NOT Empty
					if ( strlen( $amList ) > 5 ) {
						$wpdb->update(
									$campTable, 
									array (
										'amenities' => $amList,
										'camp_updated' => $nowDate
										),
									array (
										'idcoc_campground_details' => $cocResults->idcoc_campground_details
										),
									array (
										'%s',
										'%s'
										)
									);
					}
				}
				
				//grab updated info to use
				$cocResults = $wpdb->get_row($sql);
				
			}
		}
		
		//data updated and grabbed - assign vars
		$cgID = $cocResults->idcoc_campground_details;
		$res = $cocResults->res;
		$resURL = $cocResults->reserve_url;
		$campName = $cocResults->coc_campground_name;
		$driveDir = $cocResults->driving_directions;
		$elevation = number_format($cocResults->elevation).' Feet';
		$forestryName = $cocResults->coc_forestry_name;
		$forestryURL = $cocResults->coc_forestry_url;
		$regionName = $cocResults->coc_camp_region_name;
		$rMapURL = $cocResults->map_url;
		$campsites = $cocResults->campsites;
		$season = $cocResults->season;
		$latLoc = $cocResults->lat;
		$lonLoc = $cocResults->lon;
		$amList = $cocResults->amenities;
		
		// creates google maps link
		$colMapURL = 'https://www.google.com/maps/d/viewer?mid=1NOd9T5rPujjSZ1h7bQ0CX_x-jEE&z=13&ll='.$latLoc.'%2C'.$lonLoc;
				
		//if you can make a resurvation, get the URL and Build Button Stuff
		if( $res == 1 ) {
			//builds HTML for reservation button
			$reserveLink = '<a href="'.$resURL.'" class="btn btn-large btn-block btn-primary" role="button" title="Click here to make a camping reservation." target="_blank">Reserve a Campsite at '.$campName .' Today! <i class="fa fa-external-link"></i></a>';		
		}

		//starts echo of HTML block back to WP
		ob_start();

		if ( !empty( $jButton ) ) {
			echo $reserveLink;
		} else {
	
			echo $reserveLink;
	?>		
			<div>
				<div class="row-fluid" style="border-bottom: 1px solid #CCC">
					<div class="span3"><strong>Region:</strong></div>
					<div class="span9"><a href="<?php echo $rMapURL ?>" title="<?php echo $regionName ?>" target="new" rel="nofollow"><?php echo $regionName ?> <i class="fa fa-external-link"></i></a></div>
				</div>
				<div class="row-fluid" style="border-bottom: 1px solid #CCC">
					<div class="span3"><strong>Coordinates:</strong></div>
					<div class="span9"><?php echo $latLoc ?>,<?php echo $lonLoc ?></div>
				</div>
				<div class="row-fluid" style="border-bottom: 1px solid #CCC">
					<div class="span3"><strong>Driving Directions:</strong></div>
					<div class="span9"><?php echo $driveDir ?></div>
				</div>
				<div class="row-fluid" style="border-bottom: 1px solid #CCC">
					<div class="span3"><strong>Map Link:</strong></div>
					<div class="span9"><a href="<?php echo $colMapURL ?>" title="Camp Out Colorado Camping Map" target="new" rel="nofollow">Camp Out Colorado Camping Map <i class="fa fa-external-link"></i></a></div>
				</div>
				<div class="row-fluid" style="border-bottom: 1px solid #CCC">
					<div class="span3"><strong>Forestry:</strong></div>
					<div class="span9"><a href="<?php echo $forestryURL ?>" title="<?php echo $forestryName ?>" target="new" rel="nofollow"><?php echo $forestryName ?> <i class="fa fa-external-link"></i></a></div>
				</div>
				<div class="row-fluid" style="border-bottom: 1px solid #CCC">
					<div class="span3"><strong>Elevation:</strong></div>
					<div class="span9"><?php echo $elevation ?></div>
				</div>
				<div class="row-fluid" style="border-bottom: 1px solid #CCC">
					<div class="span3"><strong>Campsites:</strong></div>
					<div class="span9"><?php echo $campsites ?></div>
				</div>
				<div class="row-fluid" style="border-bottom: 1px solid #CCC">
					<div class="span3"><strong>Season:</strong></div>
					<div class="span9"><?php echo $season ?></div>
				</div>
				<div class="row-fluid" style="border-bottom: 1px solid #CCC">
					<div class="span3"><strong>Activities and Amenities:</strong></div>
					<div class="span9"><?php echo $amList ?></div>
				</div>
<?php
//adding a line of data if there is any water nearby
				$localWater = coc_findNearbyWater ( $latLoc, $lonLoc );
				if ( $localWater != NULL ) { ?>
						<div class="row-fluid" style="border-bottom: 1px solid #CCC">
							<div class="span3"><span title="Fishing spots within 5 miles of the campground."><strong>Fishing:</strong></span></div>
							<div class="span9">
<?php 
								foreach ( $localWater as $lw ) {
									echo '<a href="'.$lw->fishing_atlas_url.'" target="_blank" ><strong>'.$lw->water_name.'</strong> <i class="fa fa-external-link"></i></a>';
									echo '  ';
									echo coc_stocked_lvl( $lw->idcoc_water_details );
									echo '&nbsp &nbsp &nbsp';
									if ( !empty( $lw->review_url ) ){
										echo '<i class="fa fa-hand-o-right" aria-hidden="true"></i> <a href="..'.$lw->review_url.'">Read our camping review! <i class="fa fa-file-text-o"></i></a>';
									}
									echo '</br >';
								} ?>
							</div>
						</div>
<?php
				}

				//find any near by campgrounds to display
				$localCampgrounds = coc_findNearbyCamps ( $cgID, $latLoc, $lonLoc );
				if ( $localCampgrounds != NULL ) { ?>
						<div class="row-fluid" style="border-bottom: 1px solid #CCC">
							<div class="span3"><span title="Other campgrounds within 10 miles." ><strong>More Camping:</strong></span></div>
							<div class="span9">
<?php 
								foreach ( $localCampgrounds as $lc ) {
									echo '<a href="'.$lc->reserve_url.'" target="_blank" ><strong>'.$lc->coc_campground_name.'</strong> <i class="fa fa-external-link"></i></a>';
									echo '&nbsp &nbsp &nbsp';
									if ( !empty( $lc->review_url ) ){
										echo '<i class="fa fa-hand-o-right" aria-hidden="true"></i> <a href="..'.$lc->review_url.'">Read our camping review! <i class="fa fa-file-text-o"></i></a>';
									}
									echo '</br >';
								} 
?>
							</div>
						</div>

<?php
				}
?>                    

</div>
	<?php 	echo $reserveLink;
		}

		return ob_get_clean();
		
	} else {
		return 'No Park Identified';		
	}
}


?>
