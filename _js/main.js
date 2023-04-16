$(document).ready(function(){

	$('.navbar-toggle').on('click', function(){
		$('div.menu').toggleClass('open');
		$('body').toggleClass('freeze');
	});
	
});