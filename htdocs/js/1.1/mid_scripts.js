/*jshint -W117 */

$(document).ready(function(){

	//LOADBAR AND REDIRECT FOR M-ID AUTHENTICATION TIMEOUT

	//SET SECONDS TIL REDIRECT
	var sec = 1200;
	//SET INITAL PERCENTAGE
	var percentage = 100;
	//GET DECREMENT STEP SIZE
	var d = percentage / sec;


	//FUNCTION THAT COUNTS DOWN TIME AND REDIRECTS WHEN TIME IS UP
	function myTimer() {
		//console.log(percentage);
		percentage -= d;

		if (percentage > 0) {
			return percentage;
		}
		else {
			return window.location = "https://auth-test.ttu.ee/login/no-intra-et/portal-dev";
		}
	}
	//ANIMATE PROGRESS BAR
	setInterval(function(){
		$("#progress-bar").css("width", myTimer() + "%");
	},
				//SET FUNCTION EXECUTION TO 1 SECOND INTERVAL
				100
			   );

});

