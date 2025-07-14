<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
 
class Moneyquiz {
	
	/**
	 * Activate plugin and install Db tables 
	 * and insert default data
	*/
	public static function mq_plugin_activation() {  
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$mq_money_coach_status = get_option('mq_money_coach_status');
		
		// if plugin is first time installing or earlier uninstalled/deleted
		if(empty($mq_money_coach_status) || $mq_money_coach_status === false ){
			
			$table_name_money_template = $table_prefix.TABLE_MQ_MONEY_LAYOUT;
		// money quiz template setting table
		//$wpdb->query( "DROP TABLE ".$table_prefix.TABLE_MQ_MONEY_LAYOUT );
		$sql = "CREATE TABLE  ".$table_prefix.TABLE_MQ_MONEY_LAYOUT." (
			`Moneytemplate_ID` int(11) NOT NULL AUTO_INCREMENT,
			`field` varchar(1000),
			`value` varchar(1000),
			PRIMARY KEY (`Moneytemplate_ID`)
		) ".$charset_collate." ;";
		dbDelta($sql);
		$money_quiz_template_data= array();
		$money_quiz_template_data[] =	array( 
			'field' => "banner_image",  
			'value' => plugins_url('assets/images/mind-full-money-banner-image.jpg', __FILE__), 
		);
		$money_quiz_template_data[] =	array( 
			'field' => "money_quiz_column_image",  
			'value' => plugins_url('assets/images/quiz.webp', __FILE__)
		);
		$money_quiz_template_data[] =	array( 
			'field' => "minfull_money_gift_one",  
			'value' => plugins_url('assets/images/gift.svg', __FILE__) 
		);
		$money_quiz_template_data[] =	array( 
			'field' => "minfull_money_gift_two",  
			'value' => plugins_url('assets/images/gift.svg', __FILE__) 
		);
		/**new mapping field */

		$money_quiz_template_data[] =	array( 
			'field' => "banner_heading_text",  
			'value' => "Take the Money Quiz"
		);

		$money_quiz_template_data[] =	array( 
			'field' => "banner_quiz_content",  
			'value' => "Based on the work of Carl Jung on Archetypes and the Collective Unconscious", 
		);
		$money_quiz_template_data[] =	array( 
			'field' => "minfull_money_gift_two_column_heading",  
			'value' => "Is your Money Mindset holding you back?" 
		);
		
		
		$money_quiz_template_data[] =	array( 
			'field' => "minfull_money_bottom",  
			'value' => "And, to get your abundance flowing right away, <br>I have not one, but two free gifts for you." 
		);
		$money_quiz_template_data[] =	array( 
			'field' => "minfull_money_gift_one_headig", 
			'value' => "Gift 1" 
		);
		$money_quiz_template_data[] =	array( 
			'field' => "minfull_money_gift_two_heading",  
			'value' => "Gift 2"
		);
		$money_quiz_template_data[] =	array( 
			'field' => "minfull_money_gift_one_content",  
			'value' => "no strings attached, value packed 30-minute 1:1 Discovery call. During this call we’ll discuss your quiz results in detail, and we’ll look at some actions you can implement straight away to start changing your money mindset." 
		);
		$money_quiz_template_data[] =	array( 
			'field' => "minfull_money_gift_two_content",  
			'value' => "<p><strong class='rose'>A FREE</strong> downloadable resource – choose from over 30 downloads to help transform your Money Story.</p>" 
		);
		$money_quiz_template_data[] =	array( 
			'field' => "minfull_money_bottom_section_content",  
			'value' => "Click below and get ready to see your relationship with money in a whole new way!" 
		);
		$money_quiz_template_data[] =	array( 
			'field' => "minfull_money_gift_one_display",  
			'value' => "Yes"
		);
		$money_quiz_template_data[] =	array( 
			'field' => "minfull_money_gift_two_display",  
			'value' => "Yes"
		);
		$money_quiz_template_data[] =	array( 
			'field' => "mindfull_money_button_text",  
			'value' => "Take the Money Quiz Now"
		);
		$money_quiz_template_data[] =	array( 
			'field' => "two_column_heading_content",  
			'value' => "<li><p>Take the Money Quiz and see if your money mindset is holding you back from realising your full potential.</p></li> 
						<li><p>This comprehensive quiz is based on the work of Carl Jung, a Swiss psychiatrist, who used the concept of Archetypes to illuminate behavioural patterns. With these insights, I can help you transform your reality into one of abundance.</p></li>
						<li><p>Please note that in order to view your results, you’ll need to provide your name and email, but I can assure you, your details are strictly confidential and you only get added to my newsletter if you request it.</p></li>" 
		);
		/** end */			
		// insert default data into mq coach table	
		foreach($money_quiz_template_data as $data){
			$field = $data['field'];
			$value = $data['value'];
			$wpdb->insert( 
				$table_prefix.TABLE_MQ_MONEY_LAYOUT,
				array(
					"field" => $field,
					"value" => $value
					
				 )
			);
		} 

			// MQ prospects table
			$sql = "CREATE TABLE  ".$table_prefix.TABLE_MQ_PROSPECTS." (
			  `Prospect_ID` int(11) NOT NULL AUTO_INCREMENT,
			  `Name` varchar(255) NOT NULL,
			  `Surname` varchar(255) NOT NULL,
			  `Email` varchar(255) NOT NULL,
			  `Telephone` varchar(15) NOT NULL,
			  `Newsletter` set('Yes','No') NOT NULL DEFAULT 'No',
			  `Consultation` set('Yes','No') NOT NULL DEFAULT 'No',
			  PRIMARY KEY (`Prospect_ID`)
			) ".$charset_collate." ;";
			dbDelta($sql);
			
			// MQ coach table 
			$sql = "CREATE TABLE  ".$table_prefix.TABLE_MQ_COACH." (
			  `ID` int(11) NOT NULL AUTO_INCREMENT,
			  `Field` varchar(555) NOT NULL,
			  `Value` text NOT NULL,
			  PRIMARY KEY (`ID`)
			) ".$charset_collate." ;";
			dbDelta($sql);
			
			// Create CTA table 
			$sql = "CREATE TABLE  ".$table_prefix.TABLE_MQ_CTA." (
			  `ID` int(11) NOT NULL AUTO_INCREMENT,
    		  `Section` varchar(55) NOT NULL,
			  `Field` varchar(555) NOT NULL,
			  `Value` text NOT NULL,
			  PRIMARY KEY (`ID`)
			) ".$charset_collate." ;";
			dbDelta($sql);
			
			// MQ master table 
			$sql="CREATE TABLE  ".$table_prefix.TABLE_MQ_MASTER." (
			  `Master_ID` int(11) NOT NULL AUTO_INCREMENT,
			  `ID_Group` int(11) NOT NULL,
			  `ID_Category` int(11) NOT NULL,
			  `ID_Question` varchar(255) NOT NULL,
			  `ID_Unique` varchar(11) NOT NULL,
			  `Group` varchar(255) NOT NULL,
			  `Category` varchar(255) NOT NULL,
			  `Archetype` int(11) NOT NULL,
			  `Question` varchar(55) NOT NULL,
			  `Definition` text NOT NULL,
			  `Example` text NOT NULL,
			  `Blitz` varchar(5) NOT NULL,
			  `Short` varchar(5) NOT NULL,
			  `Full` varchar(5) NOT NULL,
			  `Classic` varchar(5) NOT NULL,
			  `Version` varchar(255) NOT NULL,
			  PRIMARY KEY (`Master_ID`)
			) ".$charset_collate." ;";
			dbDelta($sql);
			
			// MQ results table
			$sql="CREATE TABLE  ".$table_prefix.TABLE_MQ_RESULTS." (
			  `Results_ID` int(11) NOT NULL AUTO_INCREMENT,
			  `Prospect_ID` int(11) NOT NULL,
			  `Taken_ID` int(11) NOT NULL,
			  `Master_ID` int(11) NOT NULL,
			  `Score` int(11) NOT NULL,
			  PRIMARY KEY (`Results_ID`)
			) ".$charset_collate." ;";
			dbDelta($sql);

			// MQ results taken table
			$sql="CREATE TABLE  ".$table_prefix.TABLE_MQ_TAKEN." (
			  `Taken_ID` int(11) NOT NULL AUTO_INCREMENT,
			  `Prospect_ID` int(11) NOT NULL,
			  `Date_Taken` varchar(50) NOT NULL,
			  `Quiz_Length` varchar(15) NOT NULL,
			  PRIMARY KEY (`Taken_ID`)
			) ".$charset_collate." ;";
			dbDelta($sql);

			// MQ Archetypes table 
			$sql = "CREATE TABLE  ".$table_prefix.TABLE_MQ_ARCHETYPES." (
			  `ID` int(11) NOT NULL AUTO_INCREMENT,
			  `Field` varchar(555) NOT NULL,
			  `Value` text NOT NULL,
			  PRIMARY KEY (`ID`)
			) ".$charset_collate." ;";
			dbDelta($sql);
			
			// insert data into Archetypes table
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Name_1", 
				'Value' => "Hero", 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Image_1",  
				'Value' => plugins_url('assets/images/Hero.jpg', __FILE__), 
			);	
 			$data_insert_2[] =	array( 
				'Field' => "Archetype_Short_Description_1", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Hero Archetype</span> embodies the spirit of conquering the money world with a focus on success in business and financial endeavors. <span style="font-weight: bold;">Heroes</span> are skilled investors, decisive, and trust their instincts. They recognize growth opportunities in conflicts, seeing worthy opponents not as adversaries but as teachers. Embracing these lessons leads to personal and financial transformation. Whether negotiating skillfully or aiming to win, Heroes thrive in the business arena.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Long_description_1", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Hero Archetype</span> represents the fearless conqueror of the money world, thriving in business and financial success. <span style="font-weight: bold;">Heroes</span> are adept investors, highly focused, and decisively in control of their financial journeys. While they value advice, they ultimately trust their instincts and resources to guide them.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">Heroes</span> often face challenges distinguishing between adversaries and worthy opponents. A <span style="font-weight: bold;">worthy opponent</span> offers an opportunity for growth and transformation, disguised as conflict. This person, often the source of our greatest challenges, holds valuable lessons for us. When we embrace these lessons, we recognize that our "opponent" has, in fact, served us well.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">Heroes</span> flourish by viewing conflicts as opportunities for personal and financial growth. They navigate the business world with a keen eye for negotiation and a desire to win, but they also understand the importance of growth and transformation. Whether enjoying the sport of business or aiming to triumph at any cost, <span style="font-weight: bold;">Heroes</span> embody resilience and success in their financial endeavors.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">Embrace your inner <span style="font-weight: bold;">Hero</span> and see every challenge as a stepping stone to greater heights. Recognize the lessons hidden in conflicts and allow them to fuel your journey toward unparalleled success.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Name_2", 
				'Value' => "Artist", 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Image_2",  
				'Value' => plugins_url('assets/images/Artist.jpg', __FILE__), 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Short_Description_2", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Creative Artist Archetype</span> navigates a spiritual or artistic path, often struggling with a love/hate relationship with money. <span style="font-weight: bold;">Creative Artists</span> love the freedom money brings but resist engaging fully in the material world. Their challenge lies in integrating their spiritual and material worlds, which can unlock their potential for financial abundance. Embracing both aspects of life, <span style="font-weight: bold;">Creative Artists</span> can transform their inner journey into tangible success.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Long_description_2", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Creative Artist Archetype</span> embodies a deep connection to the spiritual or artistic path, often finding the material world challenging and conflicting. <span style="font-weight: bold;">Creative Artists</span> have a complex relationship with money: they love the freedom it provides but resist participating fully in the material aspects of life. This tension creates a block to the financial freedom they desire.</p>
<p style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span lang="en-US" style="font-weight: bold;">Creative Artists</span><span lang="en-US"> fear being inauthentic and not true to themselves. Their struggle for financial survival isn</span><span lang="en-CH">’</span><span lang="en-US">t due to a lack of talent or ambition but rather a belief system that disempowers their ability to manifest money. Many on this path view money as lacking in spirituality or as inherently negative, a belief that limits their financial potential.</span></p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">Creative Artists</span> possess the qualities necessary to become <span style="font-weight: bold;">Magicians</span> by integrating the spiritual with the material world. They must embrace the world in all its dimensions, recognizing that both spiritual and material aspects are part of their duality. By accepting and balancing these worlds, <span style="font-weight: bold;">Creative Artists</span> can end their struggles and achieve the financial freedom they seek.</p>
<p style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span lang="en-US">Embrace your inner </span><span lang="en-US" style="font-weight: bold;">Creative Artist</span><span lang="en-US"> and see the material world as a complement to your spiritual journey. Recognize that financial abundance and artistic integrity can coexist, and allow this understanding to transform your relationship with money. By integrating these aspects, you</span><span lang="en-CH">’</span><span lang="en-US">ll unlock the potential for a life filled with both creative fulfillment and financial success.</span></p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Name_3", 
				'Value' => "Ruler", 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Image_3",  
				'Value' => plugins_url('assets/images/Ruler.jpg', __FILE__), 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Short_Description_3", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Ruler Archetype</span> uses money to control and manipulate people, events, and circumstances. Despite having material wealth, <span style="font-weight: bold;">Rulers</span> often feel incomplete and uneasy, driven by a constant fear of losing control. Their journey involves transforming their need for dominance into a more balanced approach to power and influence, finding true fulfillment beyond financial success.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Long_description_3", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Ruler Archetype</span> is characterized by a strong desire to control and manipulate people, events, and circumstances using money. <span style="font-weight: bold;">Rulers</span> hoard wealth, using it as a tool to maintain dominance over others. Despite having everything they could desire, they often feel incomplete, uncomfortable, and never truly at peace. Their greatest fear is the loss of control.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">Rulers</span> are often overdeveloped <span style="font-weight: bold;">Heroes</span> who have become excessively invested in their need for control and dominance. While <span style="font-weight: bold;">Heroes</span> are typically concerned with the welfare of others, <span style="font-weight: bold;">Rulers</span> are purely self-interested, seeking power and control for its own sake. They may forsake others to gain more power, emerging as political leaders, businesspeople, or family figureheads who dominate and manipulate without remorse.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">Throughout history, the <span style="font-weight: bold;">Ruler</span> has been seen as a dominant and destructive force, often using whatever means necessary to win at all costs. Today, they continue to shape our perception of financial success, reinforcing the belief that money is the root of all evil. This societal image makes many hesitate to pursue wealth, fearing they might become like those who misuse it.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">Despite their apparent success, <span style="font-weight: bold;">Rulers</span> are often not as rich as they seem. They possess material wealth and can buy anything money can offer, but they lack many things that money cannot buy. They suffer from what can be called <span style="font-weight: bold;">chronic-not-enoughness,</span> always feeling fearful and unfulfilled despite their wealth.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">The journey of a <span style="font-weight: bold;">Ruler</span> involves transforming their need for dominance into a more balanced approach to power and influence. By shifting their focus from control to genuine leadership and recognizing the value of emotional and spiritual wealth, <span style="font-weight: bold;">Rulers</span> can find true fulfillment and peace. Embracing this transformation allows them to experience a deeper sense of satisfaction beyond financial success, leading to a more harmonious and enriched life.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Name_4", 
				'Value' => "Innocent", 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Image_4",  
				'Value' => plugins_url('assets/images/Innocent.jpg', __FILE__), 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Short_Description_4", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Innocent Archetype</span> tends to avoid money matters, often living in denial and relying heavily on others for financial decisions. They are trusting and easily overwhelmed by financial information, resembling small children in their naivety. The journey of the Innocent involves learning to face financial realities and develop discernment, transforming their vulnerability into a source of strength and empowerment.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Long_description_4", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Innocent Archetype</span> takes an ostrich approach to money matters, often living in denial and burying their heads in the sand to avoid facing financial realities. <span style="font-weight: bold;">Innocents</span> are easily overwhelmed by financial information and heavily rely on the advice and opinions of others. This archetype is perhaps the most trusting of all the money archetypes, as they do not see people or situations for what they truly are.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">Innocents</span> are not unlike small children in their naivety, having not yet learned to judge or discern others motives or behavior. This trait, while endearing, is precarious for an adult trying to cope in the real world. Their childlike trust and simplicity can leave them vulnerable to being misled or taken advantage of.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">We all start our journey in life as <span style="font-weight: bold;">Innocents</span>. However, as we grow and develop, the veil of innocence is lifted and replaced by our experiences with the outer world. <span style="font-weight: bold;">Innocents</span> must learn to navigate these experiences, facing financial realities and developing the discernment needed to make informed decisions.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">The journey of an <span style="font-weight: bold;">Innocent</span> involves moving from a place of denial and dependency to one of awareness and empowerment. By learning to confront financial matters head-on and developing a more discerning approach to money and people, <span style="font-weight: bold;">Innocents</span> can transform their vulnerability into a source of strength. This transformation allows them to embrace their financial journey with confidence, making informed decisions that lead to greater stability and security.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">Through this process, <span style="font-weight: bold;">Innocents</span> can retain their trusting and positive nature while gaining the skills and knowledge needed to thrive in the real world. Embracing this journey helps them build a healthy relationship with money, empowering them to make choices that support their well-being and future success.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Name_5", 
				'Value' => "Maverick", 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Image_5",  
				'Value' => plugins_url('assets/images/Meverick.jpg', __FILE__), 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Short_Description_5", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Maverick Archetype</span> is a daring gambler, always seeking financial shortcuts and windfalls. Combining traits of the Innocent and the Hero, Mavericks are fearless, optimistic, and adventurous but often lack discipline and foresight. They view money-making as a sport, frequently taking risks without considering long-term consequences. With mastery, Mavericks can transform into Magicians, channeling their boldness into sustained success.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Long_description_5", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Maverick Archetype</span> plays by a different set of rules altogether. A natural gambler, the Maverick is always on the lookout for a financial windfall by taking shortcuts and daring risks. <span style="font-weight: bold;">Mavericks</span> often live by the adage "a fool and his money are soon parted," yet they frequently win because they are willing to throw the dice and take chances that others might avoid.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">The Maverick is a unique blend of the <span style="font-weight: bold;">Innocent</span> and the <span style="font-weight: bold;">Hero</span>. Like the Innocent, the Maverick can be judgment-impaired and struggles to see the truth about situations. However, the Mavericks adventurer spirit and fearlessness set them apart. They get caught up in the enthusiasm of the moment, caring little for the details, and remain eternal optimists regardless of the circumstances.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">Unlike the Innocent, <span style="font-weight: bold;">Mavericks</span> are relatively fearless in their endeavors. They share the <span style="font-weight: bold;">Heros</span> resilience, often landing on their feet and not easily defeated. However, they lack the Heros discipline and focus. For <span style="font-weight: bold;">Mavericks</span>, money-making is more of a sport or a form of recreation rather than a serious endeavor. They would happily give the shirt off their backs, only to realize later that it wasnt their shirt or that it was their last one.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">The Maverick possesses some remarkable qualities. They live very much in the moment and are quite unattached to future outcomes. Most of what <span style="font-weight: bold;">Mavericks</span> pursue is for the simple pleasure of doing it. This characteristic of living in the moment is something many of us could learn from. However, until the Maverick becomes enlightened, they will continue to attract money easily, only to have it slip through their fingers because they are simply not paying attention.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">The journey of a <span style="font-weight: bold;">Maverick</span> involves learning to channel their adventurous spirit and optimism into more sustained and thoughtful financial practices. By mastering their boldness and learning to pay attention to the details, <span style="font-weight: bold;">Mavericks</span> can transform into <span style="font-weight: bold;">Magicians</span>, capable of creating lasting success. Embracing this transformation allows <span style="font-weight: bold;">Mavericks</span> to combine their natural risk-taking abilities with strategic thinking, leading to a more stable and prosperous financial future.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Name_6", 
				'Value' => "Victim", 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Image_6",  
				'Value' => plugins_url('assets/images/Victim.jpg', __FILE__), 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Short_Description_6", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Victim Archetype</span> often lives in the past, blaming financial woes on external factors and expecting others to rescue them. They carry a sense of entitlement, believing their suffering entitles them to compensation. Despite real hardships, their inability to face and process pain keeps them stuck. With the right support, Victims can learn to take responsibility and transform their mindset.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Long_description_6", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Victim Archetype</span> is prone to living in the past and blaming their financial woes on external factors. Often displaying passive-aggressive behavior, Victims act out their feelings indirectly rather than through direct action. They can appear disguised as <span style="font-weight: bold;">Innocents</span> because they seem powerless and appear to want others to take care of them. However, this appearance is often either a conscious or subconscious ploy to get others to do for them what they refuse to do for themselves.</p>
<p style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span lang="en-US">Victims typically have a litany of excuses for why they are not more successful, all based on their historical mythology. This does not mean that bad things haven</span><span lang="en-CH">’</span><span lang="en-US">t actually happened to them. More often than not, </span><span lang="en-US" style="font-weight: bold;">Victims</span><span lang="en-US"> have experienced abuse, betrayal, or significant loss. The problem is that they have never processed or faced their pain, which has subsequently turned on them.</span></p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">Victims</span> are always looking for someone to rescue them, believing they have suffered enough. They carry a sense of entitlement: "I paid my dues, look at my battle scars, wheres my due?" This mindset keeps them stuck in a cycle of dependency and unfulfilled potential.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">The journey for a <span style="font-weight: bold;">Victim</span> involves learning to process and face their pain, taking responsibility for their actions, and transforming their mindset. By doing so, they can move from a place of feeling powerless to one of empowerment. Embracing this transformation allows <span style="font-weight: bold;">Victims</span> to break free from their past and create a future filled with possibility and abundance.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">With the right support and guidance, <span style="font-weight: bold;">Victims</span> can learn to recognize their worth, develop resilience, and take proactive steps toward their goals. This shift not only improves their financial situation but also enhances their overall well-being, leading to a more fulfilled and empowered life.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Name_7", 
				'Value' => "Magician", 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Image_7",  
				'Value' => plugins_url('assets/images/Magician.jpg', __FILE__), 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Short_Description_7", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Magician Archetype</span> represents the ideal money type, adept at transforming and manifesting financial reality by harmonizing material and spiritual dynamics. Magicians are fully aware, at peace with their past, and understand their power lies in their truth and connection to a Higher Power. They embody spiritual wealth and manifest abundance in the material world through faith, love, and patience.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Long_description_7", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Magician Archetype</span> is the epitome of financial mastery. Magicians skillfully navigate both the material world and the realm of the Spirit, transforming and manifesting their financial reality with ease. At our best, when we fully claim our power, we can all embody the traits of a Magician.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">Understanding your current money type and personal mythology is essential for growth. By becoming conscious of the patterns and behaviors that hinder your financial relationship, you can begin to transform your reality. The Magician is fully awake, self-aware, and attuned to the world around them. They have reconciled their past, embracing their personal history, and recognizing that their true power lies in understanding and living their truth.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">Magicians know that the power to manifest lies in their ability to connect with their Higher Power. With faith, love, and patience, they confidently wait, knowing that their needs are always met. The Magician embraces the inner life as a source of spiritual wealth and views the outer life as an expression of enlightenment in the material world. These two aspects are infinitely connected, creating a harmonious balance between the spiritual and material realms.</p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">As a <span style="font-weight: bold;">Magician</span>, you understand that your journey involves continuous growth and transformation. By tapping into your higher consciousness, you can effortlessly manifest abundance and prosperity. Your ability to see and live the truth of who you are allows you to harness your inner power and create the financial reality you desire. Embracing the Magician within enables you to lead a life of abundance, spiritual fulfillment, and material success, ultimately achieving a state of harmony and enlightenment.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Name_8", 
				'Value' => "Martyr", 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Image_8",  
				'Value' => plugins_url('assets/images/Martyr.jpg', __FILE__), 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Short_Description_8", 
				'Value' => '<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span style="font-weight: bold;">The Martyr Archetype</span> is characterized by a deep commitment to helping others, often at the expense of their own needs. Martyrs tend to be perfectionists with high expectations, leading to repeated disappointments. Their focus on others needs and their attachment to suffering can hinder their personal growth. However, Martyrs who address their own wounds can become powerful healers and manifestors, transforming into money Magicians.</p>', 
			);	
			$data_insert_2[] =	array( 
				'Field' => "Archetype_Long_description_8", 
				'Value' => '<p style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span lang="en-US" style="font-weight: bold;">The Martyr Archetype</span><span lang="en-US"> embodies a self-sacrificing nature, consistently putting the needs of others before their own. Financially, Martyrs often prioritize rescuing others</span><span lang="en-CH">—</span><span lang="en-US">whether its a child, spouse, friend, or partner</span><span lang="en-CH">—</span><span lang="en-US">over attending to their own needs. They are characterized by a dual energy: one that seeks control and perfection, and another that resonates with the wounded, needy child within.</span></p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">Martyrs are perfectionists with high expectations, both for themselves and others. This trait makes them capable of realizing their dreams, as they invest substantial energy into being right and achieving their goals. However, this also leads to repeated disappointments when others fail to meet their expectations. The Martyrs unconscious attachment to suffering often results in a cycle of high drama, experiencing intense highs and lows.</p>
<p style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span lang="en-US">Their focus on the negative aspects of life</span><span lang="en-CH">—</span><span lang="en-US">seeing the glass as half empty</span><span lang="en-CH">—</span><span lang="en-US">prevents Martyrs from recognizing the profound wisdom that lies within their experiences. This attachment to negative experiences can keep them from realizing their full potential. However, Martyrs who are willing to do the inner work to heal their wounds can transform their lives significantly.</span></p>
<p lang="en-US" style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">Martyrs who embrace their healing journey have the potential to become gifted healers and powerful manifestors. By shifting their focus from the negative to the positive and addressing their inner wounds, they can harness their deep wisdom and energy to manifest abundance and success. These Martyrs can evolve into <span style="font-weight: bold;">money Magicians</span>, using their experiences and insights to create a fulfilling and prosperous life.</p>
<p style="margin: 0in; font-family: Calibri; font-size: 11.0pt;"><span lang="en-US">Embracing the Martyr Archetype involves recognizing the need for balance</span><span lang="en-CH">—</span><span lang="en-US">taking care of oneself while helping others. By doing so, Martyrs can transform their relationship with money and achieve both personal and financial growth. This journey of self-discovery and healing empowers them to become influential and compassionate leaders, capable of making a significant impact in their own lives and the lives of others.</span></p>', 
			);	
	
	// ideal score field 
			$data_insert_2[] =	array( 
				'Field' => "Archetype1 Ideal Score", 
				'Value' => "70%", 
			);
			$data_insert_2[] =	array( 
				'Field' => "Archetype2 Ideal Score", 
				'Value' => "40%", 
			);
			$data_insert_2[] =	array( 
				'Field' => "Archetype3 Ideal Score", 
				'Value' => "20%", 
			);
			$data_insert_2[] =	array( 
				'Field' => "Archetype4 Ideal Score", 
				'Value' => "20%", 
			);
			$data_insert_2[] =	array( 
				'Field' => "Archetype5 Ideal Score", 
				'Value' => "20%", 
			);
			$data_insert_2[] =	array( 
				'Field' => "Archetype6 Ideal Score", 
				'Value' => "10%", 
			);
			$data_insert_2[] =	array( 
				'Field' => "Archetype7 Ideal Score", 
				'Value' => "80%", 
			);
			$data_insert_2[] =	array( 
				'Field' => "Archetype8 Ideal Score", 
				'Value' => "20%", 
			);
			
			// insert default data into mq coach table	
			foreach($data_insert_2 as $data){
				$wpdb->insert( 
					$table_prefix.TABLE_MQ_ARCHETYPES,
					$data
				);
			} 
			
			
			// insert data into master table
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '01', 
				'ID_Unique' => 'ID1101', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 29, 
				'Question' => 'Caretaker', 
				'Definition' => 'Always takes care of others', 
				'Example' => 'Let me take care of that for you', 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '02', 
				'ID_Unique' => 'ID1102', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 17, 
				'Question' => 'Controlling', 
				'Definition' => 'Always seeks to be in charge of every situation and can be manipulative to do so', 
				'Example' => 'Let\'s do it my way', 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '03', 
				'ID_Unique' => 'ID1103', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 1, 
				'Question' => 'Disciplined', 
				'Definition' => 'Behaves in a very controlled way and makes considered decisions', 
				'Example' => "I want the new iPad but I'll need to save up before I can buy it", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '04', 
				'ID_Unique' => 'ID1104', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 25, 
				'Question' => 'Enlightened', 
				'Definition' => 'In tune with oneself and certain of who they are', 
				'Example' => "I am exactly where I want to be in life", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '05', 
				'ID_Unique' => 'ID1105', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 21, 
				'Question' => 'Financially irresponsible', 
				'Definition' => "Doesn't think about the consequences of spending or giving away money", 
				'Example' => "I just keep going until I max out my credit card each month", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '06', 
				'ID_Unique' => 'ID1106', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 13, 
				'Question' => 'Happy-go-lucky', 
				'Definition' => "Accepting of what happens without feeling the need to plan or drive situtations forward", 
				'Example' => "Something will turn up", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '07', 
				'ID_Unique' => 'ID1107', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 17, 
				'Question' => 'Impulsive', 
				'Definition' => "Acts suddenly without planning or considering the consequences of those actions", 
				'Example' => "Don't bother me with details - let's just go!", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '08', 
				'ID_Unique' => 'ID1108', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 5, 
				'Question' => 'Internally Motivated', 
				'Definition' => "Driven and goal oriented without needing to be pushed", 
				'Example' => "Let's just get on and do this", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '09', 
				'ID_Unique' => 'ID1109', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 21, 
				'Question' => 'Lives in past', 
				'Definition' => "Has suffered in the past and cannot let go of the memories", 
				'Example' => "I've never had enough money", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '10', 
				'ID_Unique' => 'ID1110', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 9, 
				'Question' => 'Living the high-life', 
				'Definition' => "Buys expensive things even if in secret", 
				'Example' => "I only ever wear Lacoste", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '11', 
				'ID_Unique' => 'ID1111', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 9, 
				'Question' => 'Materialistic', 
				'Definition' => "Believes that having money and possessions important and will result in happiness", 
				'Example' => "I've GOT to have the new iPad", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '12', 
				'ID_Unique' => 'ID1112', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 5, 
				'Question' => 'Passive', 
				'Definition' => "Not acting to influence or change a situation; allowing other people to be in control", 
				'Example' => "I just like a quiet life", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '13', 
				'ID_Unique' => 'ID1113', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 29, 
				'Question' => 'Perfectionist', 
				'Definition' => "Wants everything to be perfect and demands the highest standards possible", 
				'Example' => "That picture on the wall is crooked and I need to straigten it", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '14', 
				'ID_Unique' => 'ID1114', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 1, 
				'Question' => 'Powerful', 
				'Definition' => "Is able to influence other people and their actions", 
				'Example' => "I've done this many times before. Let me show you how.", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '15', 
				'ID_Unique' => 'ID1115', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 13, 
				'Question' => 'Powerless', 
				'Definition' => "Feels helpless and compelled to follow the lead of others", 
				'Example' => "I can't change because others won't let me", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 1, 
				'ID_Question' => '16', 
				'ID_Unique' => 'ID1116', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Current State', 
				'Archetype' => 25, 
				'Question' => 'Unattached', 
				'Definition' => "Free from the opinions of others and to live their own life.", 
				'Example' => "I can make my own decisions", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '01', 
				'ID_Unique' => 'ID1201', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 21, 
				'Question' => 'Blaming others', 
				'Definition' => "Believe that someone else is to blame for this situation, not me", 
				'Example' => "I'd be better of if it weren't for my run of bad luck", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '02', 
				'ID_Unique' => 'ID1202', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 1, 
				'Question' => 'Cautious', 
				'Definition' => "Considers every possible consequence of actions and avoids risks", 
				'Example' => "Have we considered all the angles?", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '03', 
				'ID_Unique' => 'ID1203', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 25, 
				'Question' => 'Confident', 
				'Definition' => "Believes in themselves and takes positive action without worrying about how others will react", 
				'Example' => "I can do this", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '04', 
				'ID_Unique' => 'ID1204', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 25, 
				'Question' => 'Conscious', 
				'Definition' => "Self aware and alert to the world around them", 
				'Example' => "I am here, in this moment and aware of what is going on", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '05', 
				'ID_Unique' => 'ID1205', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 29, 
				'Question' => 'Controlling', 
				'Definition' => "Always seeks to be in charge of every situation and can be manipulative to do so", 
				'Example' => "Let's do it my way", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '06', 
				'ID_Unique' => 'ID1206', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 9, 
				'Question' => 'I can have it all', 
				'Definition' => "Pursues their own desires and will do whatever it takes to get what they want", 
				'Example' => "I'm going to be a billionare and nothing can stop me", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '07', 
				'ID_Unique' => 'ID1207', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 17, 
				'Question' => 'Lives for today', 
				'Definition' => "Doesn't think about the future", 
				'Example' => "Life's too short to worry about bills", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '08', 
				'ID_Unique' => 'ID1208', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 21, 
				'Question' => 'Long-suffering', 
				'Definition' => "Unable to be in the present and enjoy the 'now'", 
				'Example' => "Nothing good ever happens to me", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '09', 
				'ID_Unique' => 'ID1209', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 13, 
				'Question' => 'Looking for security', 
				'Definition' => "Wants to feel safe and secure so won't take risks", 
				'Example' => "I'll wait until I can be 100% sure it will work out okay", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '10', 
				'ID_Unique' => 'ID1210', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 13, 
				'Question' => 'Looking to be rescued', 
				'Definition' => "Waiting to be bailed out out of a difficult situation, whether that is finanacial, practical or emotional", 
				'Example' => "I can't do this for myself - will you do it for me?", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '11', 
				'ID_Unique' => 'ID1211', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 1, 
				'Question' => 'Loyal', 
				'Definition' => "Always there for loved ones and others", 
				'Example' => "I'm here for you. If oyu need me, just call.", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '12', 
				'ID_Unique' => 'ID1212', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 9, 
				'Question' => 'Oppressive', 
				'Definition' => "Bullying and unfair, using their power to get their own way.", 
				'Example' => "If you don't do it my way, I'll leave", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '13', 
				'ID_Unique' => 'ID1213', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 17, 
				'Question' => 'Optimistic', 
				'Definition' => "Full of hope and can see the good in any situation, even when there are challenges", 
				'Example' => "We will find a solution to this challenge", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '14', 
				'ID_Unique' => 'ID1214', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 5, 
				'Question' => 'Reclusive', 
				'Definition' => "Lives alone and avoids going outside or talking to other people", 
				'Example' => "I hate noisy bars. I'd rather stay at home with a good book.", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '15', 
				'ID_Unique' => 'ID1215', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 5, 
				'Question' => 'Seeks the truth', 
				'Definition' => "Open to other people's opinions and interested to learn other perspectives", 
				'Example' => "What do you think? Tell me more.", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 2, 
				'ID_Question' => '16', 
				'ID_Unique' => 'ID1216', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Facing the World', 
				'Archetype' => 29, 
				'Question' => 'Unsupported', 
				'Definition' => "Feels that they have to do everything by themself without others contributing their fair share", 
				'Example' => "No one ever puts me first", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '01', 
				'ID_Unique' => 'ID1301', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 13, 
				'Question' => 'Anxious', 
				'Definition' => "Worried and nervous", 
				'Example' => "But what if something goes wrong", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '02', 
				'ID_Unique' => 'ID1302', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 17, 
				'Question' => 'Careless', 
				'Definition' => "Not taking or showing enough care and attention", 
				'Example' => "I know I had €100 this morning but I can't think where it has all gone.", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '03', 
				'ID_Unique' => 'ID1303', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 5, 
				'Question' => 'Creative', 
				'Definition' => "Producing or using original and unusual ideas", 
				'Example' => "I've got a whacky idea - let's hold the meeting in the park", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '04', 
				'ID_Unique' => 'ID1304', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 25, 
				'Question' => 'Fluid', 
				'Definition' => "Goes with the flow and is flexible", 
				'Example' => "I'm happy with either option - they are both good", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '05', 
				'ID_Unique' => 'ID1305', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 9, 
				'Question' => 'Getting your own way', 
				'Definition' => "Manipulative", 
				'Example' => "Let's see the film I want to watch this week and do what you want another time.", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '06', 
				'ID_Unique' => 'ID1306', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 13, 
				'Question' => 'Indecisive', 
				'Definition' => "Unable to make decisions", 
				'Example' => "I could buy that new dress, but I'm not sure", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '07', 
				'ID_Unique' => 'ID1307', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 1, 
				'Question' => 'Independent', 
				'Definition' => "Won't ask for help", 
				'Example' => "I'm fine. I don't need your help", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '08', 
				'ID_Unique' => 'ID1308', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 29, 
				'Question' => 'Judgmental', 
				'Definition' => "Quick to criticize themselves and others", 
				'Example' => "I don't like that shirt he's wearing", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '09', 
				'ID_Unique' => 'ID1309', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 9, 
				'Question' => 'Obsessive/compulsive', 
				'Definition' => "Keeps repeating the same actions over and over again", 
				'Example' => "I have to have the new porcelien dolls that has just come out. I've got all the others", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '10', 
				'ID_Unique' => 'ID1310', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 21, 
				'Question' => 'Passive-aggressive', 
				'Definition' => "Shows an unwillingness to be helpful or friendly, without being openly challenging", 
				'Example' => "Why are you getting so upset? I was only joking", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '11', 
				'ID_Unique' => 'ID1311', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 21, 
				'Question' => 'Prone to blame', 
				'Definition' => "Sees everything as someone else's fault", 
				'Example' => "You've ruined it", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '12', 
				'ID_Unique' => 'ID1312', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 17, 
				'Question' => 'Reckless', 
				'Definition' => "Unconcerned about the risks and consequences of their action", 
				'Example' => "I'll put all my money on 20-black", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '13', 
				'ID_Unique' => 'ID1313', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 1, 
				'Question' => 'Rescuer', 
				'Definition' => "Always seeks to help others out from a difficult situaton, often to their own detriment", 
				'Example' => "Here's €100. That will sort you out", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '14', 
				'ID_Unique' => 'ID1314', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 5, 
				'Question' => 'Resistant', 
				'Definition' => "Reluctant to accept something, especially changes or new ideas", 
				'Example' => "Why do we need to change - I like things as they are", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '15', 
				'ID_Unique' => 'ID1315', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 25, 
				'Question' => 'Resourceful', 
				'Definition' => "Skilled at solving problems and acting independently", 
				'Example' => "I can get it done", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 1, 
				'ID_Category' => 3, 
				'ID_Question' => '16', 
				'ID_Unique' => 'ID1316', 
				'Group' => 'Group1: Where are you now', 
				'Category' => 'Predisposed to…', 
				'Archetype' => 29, 
				'Question' => 'Self-sacrificing', 
				'Definition' => "Puts their own needs last", 
				'Example' => "I can't go out. I'm taking the neighbours dog to the park", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '01', 
				'ID_Unique' => 'ID2401', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 25, 
				'Question' => 'Balanced', 
				'Definition' => "Considers all sides or opinions equally", 
				'Example' => "I think Bob and Sue both have valid points to make", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '02', 
				'ID_Unique' => 'ID2402', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 21, 
				'Question' => 'Blaming others', 
				'Definition' => "Believe that someone else is to blame for this situation, not me", 
				'Example' => "I'd be better of if it weren't for my run of bad luck", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '03', 
				'ID_Unique' => 'ID2403', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 1, 
				'Question' => 'Collaborative', 
				'Definition' => "Works well with others and loves to be in a team", 
				'Example' => "Let's work on this together", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '04', 
				'ID_Unique' => 'ID2404', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 29, 
				'Question' => 'Controlling', 
				'Definition' => "Always seeks to be in charge of every situation and can be manipulative to do so", 
				'Example' => "Let's do it my way", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '05', 
				'ID_Unique' => 'ID2405', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 5, 
				'Question' => 'Detached', 
				'Definition' => "Unworldly and unconcerned about what is going on around them", 
				'Example' => "Sorry, what did you say?", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '06', 
				'ID_Unique' => 'ID2406', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 17, 
				'Question' => 'Enthusiastic', 
				'Definition' => "Excitable and ready to jump into action", 
				'Example' => "This is so exciting. I just can't wait to get started", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '07', 
				'ID_Unique' => 'ID2407', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 1, 
				'Question' => 'Generous', 
				'Definition' => "Always willing to give to others and gets pleasure from doing so", 
				'Example' => "Let's see how I can make this easier for you", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '08', 
				'ID_Unique' => 'ID2408', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 25, 
				'Question' => 'Helpful', 
				'Definition' => "Gives aid or assistance willingly and is of service to others", 
				'Example' => "Here, let me help you", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '09', 
				'ID_Unique' => 'ID2409', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 9, 
				'Question' => 'Highly critical', 
				'Definition' => "Quick to mentioned where something is wrong or does not please them", 
				'Example' => "This report isn't good enough. You'll have to do the whole thing again.", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '10', 
				'ID_Unique' => 'ID2410', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 29, 
				'Question' => 'Manipulative', 
				'Definition' => "Seeks to control other people or circumstances to their advantage", 
				'Example' => "Can I borrow your homework?", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);		
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '11', 
				'ID_Unique' => 'ID2411', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 13, 
				'Question' => 'Non-confrontational', 
				'Definition' => "Avoids getting involved in disagreements", 
				'Example' => "Let's share all our different ideas and see if we can reach a comprmise", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '12', 
				'ID_Unique' => 'ID2412', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 5, 
				'Question' => 'Often alone', 
				'Definition' => "Likes their own company", 
				'Example' => "You'll usually find me in the kitchen at parties", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '13', 
				'ID_Unique' => 'ID2413', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 17, 
				'Question' => 'Overly generous', 
				'Definition' => "Willing to give more money, help, kindness, than is usual or expected", 
				'Example' => "Make a donation for your charity? Sure. How about €1000", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '14', 
				'ID_Unique' => 'ID2414', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 9, 
				'Question' => 'Secretive', 
				'Definition' => "Hides their feelings, thoughts, intentions, and actions from other people", 
				'Example' => "I don't want anyone to know that I blew our savings on a new dress", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '15', 
				'ID_Unique' => 'ID2415', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 13, 
				'Question' => 'Trusting', 
				'Definition' => "Always believes that other people are good or honest and will not harm or deceive", 
				'Example' => "I don't need it in wirting - your word is enough for me.", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 4, 
				'ID_Question' => '16', 
				'ID_Unique' => 'ID2416', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Behaviour towards others', 
				'Archetype' => 21, 
				'Question' => 'Unforgiving', 
				'Definition' => "Not willing to forgive people for things they do wrong", 
				'Example' => "How could you have lost my favourite shirt", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '01', 
				'ID_Unique' => 'ID2501', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 17, 
				'Question' => 'Adventurous', 
				'Definition' => "Loves a gamble and to take risks", 
				'Example' => "I love a challenge", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '02', 
				'ID_Unique' => 'ID2502', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 9, 
				'Question' => 'Angry', 
				'Definition' => "Has strong feelings against other people or circumstances and wanting to fight them", 
				'Example' => "I'm so annoyed with him for upsetting Sophie", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '03', 
				'ID_Unique' => 'ID2503', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 1, 
				'Question' => 'Calculating', 
				'Definition' => "Seeks to control situations for their own advantage and thinks ahead to ensure this happens", 
				'Example' => "How can I manage this so that I come out the winner?", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '04', 
				'ID_Unique' => 'ID2504', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 29, 
				'Question' => 'Compassionate', 
				'Definition' => "Cares deeply for others and wants to see everyone living a good life.", 
				'Example' => "I am so happy for you that you are following your dream", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '05', 
				'ID_Unique' => 'ID2505', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 5, 
				'Question' => 'Conflicted', 
				'Definition' => "Cannot choose between different ideas,feelings, or beliefs, and do not know what to do or believe", 
				'Example' => "I don't know what to think", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '06', 
				'ID_Unique' => 'ID2506', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 1, 
				'Question' => 'Driven', 
				'Definition' => "Goal orientated with lots of focus and energy to achieve goals", 
				'Example' => "I'm planning to create the world's best sushi company", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '07', 
				'ID_Unique' => 'ID2507', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 13, 
				'Question' => 'Fearful', 
				'Definition' => "Frightened or worried about something", 
				'Example' => "I can't buy that - what if my husband doesn't approve", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '08', 
				'ID_Unique' => 'ID2508', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 29, 
				'Question' => 'Feels betrayed', 
				'Definition' => "Unable to trust others or let go of past events", 
				'Example' => "I can't forgive my partner for cheating on me", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '09', 
				'ID_Unique' => 'ID2509', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 9, 
				'Question' => 'Fixed in your ideas', 
				'Definition' => "Believes they are always right", 
				'Example' => "I'm doing it my way", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '10', 
				'ID_Unique' => 'ID2510', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 21, 
				'Question' => 'Highly emotional', 
				'Definition' => "Feels all emotions very intensly and can swing between them quite rapidly", 
				'Example' => "I am SOOOOO upset", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '11', 
				'ID_Unique' => 'ID2511', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 13, 
				'Question' => 'Hopeless', 
				'Definition' => "Feels there is no possiblity of a good outcome in the future", 
				'Example' => "There isn't much point in applying for that job - I neer get chosen", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '12', 
				'ID_Unique' => 'ID2512', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 25, 
				'Question' => 'Knowing your feelings', 
				'Definition' => "In tune with themselves", 
				'Example' => "I'm feeling a bit down right now - but that's okay.", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '13', 
				'ID_Unique' => 'ID2513', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 5, 
				'Question' => 'Love/Hate Relationship', 
				'Definition' => "Fnds it difficult to be in the material world", 
				'Example' => "I can't make money with my work - no one would pay for it", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '14', 
				'ID_Unique' => 'ID2514', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 17, 
				'Question' => 'Over-Indulgent', 
				'Definition' => "Allows themselve to have too much of something enjoyable, sometimes beyond the point of pleasure", 
				'Example' => "I wish I hadn't eaten the whole tub of ice-cream", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '15', 
				'ID_Unique' => 'ID2515', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 25, 
				'Question' => 'Overwhelmed', 
				'Definition' => "Allows feelings to swamp and distract them", 
				'Example' => "I can't think - there is just so much to do", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 2, 
				'ID_Category' => 5, 
				'ID_Question' => '16', 
				'ID_Unique' => 'ID2516', 
				'Group' => 'Group2: Engaging with Others', 
				'Category' => 'Emotional tendancy', 
				'Archetype' => 21, 
				'Question' => 'Resentful', 
				'Definition' => "Feels angry because they have been forced to accept someone or something that they do not like", 
				'Example' => "I always come second to your job", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '01', 
				'ID_Unique' => 'ID3601', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 9, 
				'Question' => 'Calculating', 
				'Definition' => "Seeks to control situations for their own advantage and thinks ahead to ensure this happens", 
				'Example' => "How can I manage this so that I come out the winner?", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '02', 
				'ID_Unique' => 'ID3602', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 17, 
				'Question' => 'Careless', 
				'Definition' => "Seldom takes makes care or gives attention to others", 
				'Example' => "I know I had €100 this morning but I can't think where it has all gone.", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '03', 
				'ID_Unique' => 'ID3603', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 17, 
				'Question' => 'Cup is overflowing', 
				'Definition' => "Extremely optismstic and feels that life is good", 
				'Example' => "I love my life", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '04', 
				'ID_Unique' => 'ID3604', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 1, 
				'Question' => 'Discerning', 
				'Definition' => "Able to assess and evaluate an opportunity and make good decisions about it", 
				'Example' => "I've weighed up the pors and cons and this is what I have decided", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '05', 
				'ID_Unique' => 'ID3605', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 21, 
				'Question' => 'Doomed', 
				'Definition' => "Is so convinced of their own failings that they invite their repetition", 
				'Example' => "Nothing will ever get any better in my life", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '06', 
				'ID_Unique' => 'ID3606', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 9, 
				'Question' => 'Empty', 
				'Definition' => "Unable to find satisfaction or pleasure in anything - be that money or posessions", 
				'Example' => "There is nothing good going on in my life", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '07', 
				'ID_Unique' => 'ID3607', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 1, 
				'Question' => 'Financially successful', 
				'Definition' => "Is achieving the financial results wanted or hoped for", 
				'Example' => "We have all we need and I know my family's future is secure", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '08', 
				'ID_Unique' => 'ID3608', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 29, 
				'Question' => 'Giving', 
				'Definition' => "Acts without expecting compensation", 
				'Example' => "Here, let me help you", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '09', 
				'ID_Unique' => 'ID3609', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 25, 
				'Question' => 'Guided by Faith', 
				'Definition' => "Has great trust or confidence in something or someone", 
				'Example' => "The universe will find an answer", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '10', 
				'ID_Unique' => 'ID3610', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 5, 
				'Question' => 'Non-materialistic', 
				'Definition' => "Believes that having money and possessions unimportant and that happiness comes from other things", 
				'Example' => "Money is the root of all evils", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '11', 
				'ID_Unique' => 'ID3611', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 13, 
				'Question' => 'Out of control', 
				'Definition' => "Feels that they are unable to influence their circumstances and have to respond to others", 
				'Example' => "I don't know which problem to tackle next", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '12', 
				'ID_Unique' => 'ID3612', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 21, 
				'Question' => 'Passive', 
				'Definition' => "See this as their lot in life and that nothing will change for the better", 
				'Example' => "This is my lot in life", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '13', 
				'ID_Unique' => 'ID3613', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 13, 
				'Question' => 'Repressed', 
				'Definition' => "Doesn't want to express their feelings and beliefs to themselves or others", 
				'Example' => "Nothing is the matter", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '14', 
				'ID_Unique' => 'ID3614', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 25, 
				'Question' => 'Self aware', 
				'Definition' => "Understands oneself", 
				'Example' => "I know that I a at my most productive first thing n the morning", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '15', 
				'ID_Unique' => 'ID3615', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 29, 
				'Question' => 'Self sacrificing', 
				'Definition' => "Puts their own needs last", 
				'Example' => "I gave up work to take care of my family", 
				'Blitz' => 'Yes', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Short & Full', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 6, 
				'ID_Question' => '16', 
				'ID_Unique' => 'ID3616', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Self-awareness', 
				'Archetype' => 5, 
				'Question' => 'Spiritual', 
				'Definition' => "Holds deep feelings and beliefs, which may either be religious or come from nature or from within themselves", 
				'Example' => "There is a reason for everything", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '01', 
				'ID_Unique' => 'ID3701', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 21, 
				'Question' => 'Addictive', 
				'Definition' => "Forms habits quickly but then finds them very hard to break", 
				'Example' => "I've just GOT to have chocolate", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '02', 
				'ID_Unique' => 'ID3702', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 5, 
				'Question' => 'Authentic', 
				'Definition' => "Is consistently genuine and true to their word", 
				'Example' => "I am who I am", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '03', 
				'ID_Unique' => 'ID3703', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 9, 
				'Question' => 'Direct', 
				'Definition' => "Says what is on their mind", 
				'Example' => "You look terrible in that dress", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '04', 
				'ID_Unique' => 'ID3704', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 21, 
				'Question' => 'Doomed', 
				'Definition' => "Is so convinced of their own failings that they invite their repetition", 
				'Example' => "Nothing will ever get any better in my life", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '05', 
				'ID_Unique' => 'ID3705', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 13, 
				'Question' => 'Financially dependant', 
				'Definition' => "Depends on others for financial, practical or emotional support, often a spouse or family member", 
				'Example' => "I don't understand money - Rob takes care of all that", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '06', 
				'ID_Unique' => 'ID3706', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 1, 
				'Question' => 'Goal-oriented', 
				'Definition' => "Works hard to achieve good results in the tasks that they have been given", 
				'Example' => "Keep your eyes on the prize", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '07', 
				'ID_Unique' => 'ID3707', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 29, 
				'Question' => 'Helpful', 
				'Definition' => "Gives aid or assistance willingly and is of service to others", 
				'Example' => "How can I help you with that?", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '08', 
				'ID_Unique' => 'ID3708', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 13, 
				'Question' => 'Naive', 
				'Definition' => "Ready to believe that everyone is telling the truth and has good intentions and that life is simple and fair", 
				'Example' => "Something will turn up", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '09', 
				'ID_Unique' => 'ID3709', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 29, 
				'Question' => 'Persecuted', 
				'Definition' => "Feels pessimitic and put upon often dwelling on this openly", 
				'Example' => "Everyone is always out to get me", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '10', 
				'ID_Unique' => 'ID3710', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 17, 
				'Question' => 'Restless', 
				'Definition' => "Unwilling or unable to stay still or to be quiet and calm", 
				'Example' => "I want to try something new every couple of months", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '11', 
				'ID_Unique' => 'ID3711', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 25, 
				'Question' => 'Sees opportunities', 
				'Definition' => "Always open to doing something new", 
				'Example' => "This new phone coming out will be great to use for our business", 
				'Blitz' => '', 
				'Short' => 'Yes', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '12', 
				'ID_Unique' => 'ID3712', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 9, 
				'Question' => 'Self-interested', 
				'Definition' => "Wants what they want and doesn't care about the wants and needs of others", 
				'Example' => "Me, myself and I", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '13', 
				'ID_Unique' => 'ID3713', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 5, 
				'Question' => 'Sincere', 
				'Definition' => "Genuinely believes in what they say and do", 
				'Example' => "I believe that you can achieve your dreams", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => '', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '14', 
				'ID_Unique' => 'ID3714', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 25, 
				'Question' => 'Transforms reality', 
				'Definition' => "Has a clear view of their future and is taking action to change their current state to get there", 
				'Example' => "I know where I am going and these are the steps I'm going to take", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '15', 
				'ID_Unique' => 'ID3715', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 17, 
				'Question' => 'Undisciplined', 
				'Definition' => "Behaving in a very un-controlled way", 
				'Example' => "I'm just going to… Actually perhaps I'll… ooh look, someone has posted a video of a cat on Facebook", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);	
			$data_insert[] =	array( 
				'ID_Group' => 3, 
				'ID_Category' => 7, 
				'ID_Question' => '16', 
				'ID_Unique' => 'ID3716', 
				'Group' => 'Group3: Self-Discovery', 
				'Category' => 'Upon Reflection', 
				'Archetype' => 1, 
				'Question' => 'Wise', 
				'Definition' => "Able to make good judgments, based on a deep understanding and experience of life", 
				'Example' => "Based on my experience I recommend you choose the iPad Pro", 
				'Blitz' => '', 
				'Short' => '', 
				'Full' => 'Yes', 
				'Classic' => 'Yes', 
				'Version' => 'Full Only', 
			);		
			// insert default data into mq master table	
			foreach($data_insert as $data){
				$wpdb->insert( 
					$table_prefix.TABLE_MQ_MASTER,
					$data
				);
			}
			
			// insert default data into mq coach table	
			$data_insert_1[] =	array( 
				'Field' => "Coach_ID", 
				'Value' => "CID0001", 
			);		
			$data_insert_1[] =	array( 
				'Field' => "First name", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Surname", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Title", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Professional Title", 
				'Value' => "Certified Money Coach", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Company Name", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Address", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Telephone", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Email", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Website", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "On-line Calendar Link", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Logo Image", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospects First Name", 
				'Value' => "Your first name", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospects First Name Display", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospects Surname", 
				'Value' => "Your surname", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospects Surname Display", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospects email", 
				'Value' => "Your email", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospects email Display", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospects Tel", 
				'Value' => "Your telephone", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospects Tel Display", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Newsletter", 
				'Value' => "Receive my newsletter", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Newsletter Display", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Consultation", 
				'Value' => "Receive a free consultation", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Consultation Display", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Submit button", 
				'Value' => "Send me my Money Type Report", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Additional Opening paragraph", 
				'Value' => "I\'m sure you will find this all very exciting, if you have any questions please drop me an email or chat with me on Messenger.", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Additional Closing paragraph", 
				'Value' => "Thanks once again for taking the MoneyQuiz, remember, if you have any questions drop me an email or chat with me on Facebook Messenger. Chat soon, Ilana", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Display MQ Image on Main page", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Display MQ Image on Prospect page", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Display MQ Image on Results page", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Display MQ Image on email", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Quiz Length", 
				'Value' => "Short & Full", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Participate in Group wide stats", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Header Image", 
				'Value' => plugins_url('assets/images/default-header-image.png', __FILE__),
			);	
			$data_insert_1[] =	array( 
				'Field' => "License Key", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Valid From", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Valid To", 
				'Value' => "", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Quiz Length - Blitz", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Quiz Length - Short", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Quiz Length - Full", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Quiz Length - Blitz Label", 
				'Value' => "Blitz", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Quiz Length - Short Label", 
				'Value' => "Short", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Quiz Length - Full Label", 
				'Value' => "Full", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Submit button Colour", 
				'Value' => "#008000", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Previous Button colour", 
				'Value' => "#808080", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Blitz Instructions", 
				'Value' => "This MoneyQuiz has 24 Questions split into 3 Sections with 8 Questions each and takes around 1 minute to complete. It's not as accurate as the Short or Full version, but it'll give you a good overview of the Money Archetypes, present in your life. ", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Short Instructions", 
				'Value' => "This MoneyQuiz has 56 Questions split into 7 Sections with 8 Questions each and takes less than 5 minutes to complete. This Quiz offers a good balance between the time taken to complete it and the robustness of the results.", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Full Instructions", 
				'Value' => "This MoneyQuiz has 112 Questions split into 7 Sections with 16 Questions each and takes just under 10 minutes to complete. This is the most comprehensive version of the Quiz. It will provide you with an accurate assessment and a solid framework for any follow-up questions you may have. ", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Show Ideal Score", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Ideal Score Label", 
				'Value' => "Ideal", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospects Score Label", 
				'Value' => "Yours", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Quiz Length - Classic", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Quiz Length - Classic Label", 
				'Value' => "Classic", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Quiz Length - Classic Instructions", 
				'Value' => "This is the Classic Money Institute Quiz.", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Mailing Application", 
				'Value' => "Picklist of mailing applications", 
			);
			$data_insert_1[] =	array( 
				'Field' => "URL", 
				'Value' => "", 
			);
			$data_insert_1[] =	array( 
				'Field' => "API", 
				'Value' => "", 
			);
			$data_insert_1[] =	array( 
				'Field' => "UserName", 
				'Value' => "", 
			);
			$data_insert_1[] =	array( 
				'Field' => "UserPWD", 
				'Value' => "", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Selected Mailing list", 
				'Value' => "Please choose which list you want to use", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Prospect\'s First Name Yes/No", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospect\'s First Name Field", 
				'Value' => "Select", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospect\'s Surname Yes/No", 
				'Value' => "Yes", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospect\'s Surname Field", 
				'Value' => "Select", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Prospect\'s Email Yes/No", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Prospect\'s Email Field", 
				'Value' => "Select", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Prospect\'s Tel. Yes/No", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Prospect\'s Tel. Field", 
				'Value' => "Select", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Newsletter Option Yes/No", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Newsletter Option Field", 
				'Value' => "Select", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Consultation Option Yes/No", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Consultation Option Field", 
				'Value' => "Select", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Add Tag Yes/No", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Tag Value", 
				'Value' => "MoneyQuiz", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Update Rule", 
				'Value' => "All Records,Only if Newsletter Selected", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Number of Answers Options", 
				'Value' => "Five", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Never", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Seldom", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Sometimes", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Mostly", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Always", 
				'Value' => "Yes", 
			);
			
			$data_insert_1[] =	array( 
				'Field' => "Closing Message1", 
				'Value' => "I've also sent this report to you by email.", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Before Quiz Results", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "CTA Message", 
				'Value' => "Receive a copy of your results via email", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Closing Message2", 
				'Value' => "Your report has been sent to you by email.", 
			);
			
			$data_insert_1[] =	array( 
				'Field' => "Show Progress Bar", 
				'Value' => "Yes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Progress Bar Background Colour", 
				'Value' => "#f1f1f1", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Progress Bar Main Colour", 
				'Value' => "#9e9e9e", 
			);	
			$data_insert_1[] =	array( 
				'Field' => "Progress Bar Text Colour", 
				'Value' => "#fff", 
			);	

			$data_insert_1[] =	array( 
				'Field' => "never_label", 
				'Value' => "Never", 
			);
			$data_insert_1[] =	array( 
				'Field' => "seldom_label", 
				'Value' => "Seldom", 
			);
			$data_insert_1[] =	array( 
				'Field' => "sometimes_label", 
				'Value' => "Sometimes", 
			);
			$data_insert_1[] =	array( 
				'Field' => "mostly_label", 
				'Value' => "Mostly", 
			);
			$data_insert_1[] =	array( 
				'Field' => "always_label", 
				'Value' => "Always", 
			);
			$data_insert_1[] =	array( 
				'Field' => "Submit Button Height", 
				'Value' => "40px", 
			);
		// insert default data into mq coach table	
			foreach($data_insert_1 as $data){
				$wpdb->insert( 
					$table_prefix.TABLE_MQ_COACH,
					$data
				);
			}

		// CTA table insert data
		
			$data_insert_cta[] =	array( 
				'Section' => "A: Landing Page", 
				'Field' => "Enabled", 
				'Value' => "Yes", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "A: Landing Page", 
				'Field' => "Title", 
				'Value' => "Discover Your Unique MoneyStory!", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "A: Landing Page", 
				'Field' => "Image", 
				'Value' => "https://www.mindfulmoneycoaching.com/wp-content/uploads/2018/05/banner.png", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "A: Landing Page", 
				'Field' => "Intro", 
				'Value' => "If you would like to discover your MoneyStory and find out what drives your behaviour when it comes to money decisions, then this is for you! Start your journey of self-discovery by taking the MoneyQuiz and discover which Money Archetypes are in the driving seat and which ones should be.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "A: Landing Page", 
				'Field' => "Footer", 
				'Value' => "Free, Confidential and No Obligations", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "A: Landing Page", 
				'Field' => "CTA Button Text", 
				'Value' => "Take the MoneyQuiz Now", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Enabled", 
				'Value' => "Yes", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Title", 
				'Value' => "Join Me For A One-On-One Review Of Your Money Archetypes", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Image", 
				'Value' => "https://www.mindfulmoneycoaching.com/wp-content/uploads/2018/10/All-Archetypes.jpg", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Intro", 
				'Value' => "As a Thank You for taking the MoneyQuiz I'll show you how to:", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item1 Title", 
				'Value' => "Earn More Money", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item1 Description", 
				'Value' => "I'll help you to earn more money by tapping into your natural strengths and gifts. Everyone has a unique combination of talents, and I'll help you use yours to bring more money in with less effort!", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item2 Title", 
				'Value' => "Overcome Your Money Challenges", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item2 Description", 
				'Value' => "Identifying unhealthy money behaviors is the first step towards overcoming your money challenges. You'll be amazed how small tweaks to your behavior can stop money leaks and avoid sabotaging your wins. Each archetype combination does it differently.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item3 Title", 
				'Value' => "Attract more of your ideal customers", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item3 Description", 
				'Value' => "When you learn how to market directly to their money personality and how you can naturally help them with yours. This is GOLD and why it\'s worth getting access to all 8 archetypes. It\'s like having a secret superpower.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item4 Title", 
				'Value' => "Develop a great relationship with money", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item4 Description", 
				'Value' => "Having a healthy relationship with money is essential, especially if you'd like to have lots of it. If you're going to surround yourself with lots of money, you'd better have a good relationship with it, otherwise, you will be miserable.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item5 Title", 
				'Value' => "Develop confidence in dealing with money and in your relationships with others", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item5 Description", 
				'Value' => "", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item6 Title", 
				'Value' => "Develop a great relationship with money", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item6 Description", 
				'Value' => "", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Closing", 
				'Value' => "Learn from a Certified Money Coach® serial entrepreneur, mother of 2 and wife for 31 years. I\'m at your service for the 4 week program.", 
			);
			$data_insert_cta[] =	array( 
					'Section' => "B. Benefits", 
					'Field' => "CTA Button Text", 
					'Value' => "Sound Good, Contact Me Now!", 
				);
			$data_insert_cta[] =	array( 
					'Section' => "B. Benefits", 
					'Field' => "CTA Hyperlink", 
					'Value' => "https://www.mindfulmoneycoaching.com/contact-me/", 
				);
				
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Enabled", 
				'Value' => "No", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Title", 
				'Value' => "First A Bit About Each Money Archetype", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Image", 
				'Value' => "'https://i1.wp.com/wyo-newhome.com/wp-content/uploads/2016/08/Contact-Me.png", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Intro", 
				'Value' => "Each Archetype tells a story. By knowing and understanding yours, you can transform your life and live your true purpose.", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Archetype1", 
				'Value' => "The Warrior sets out to conquer the money world and is generally seen as successful in the
business and financial worlds. Warriors are adept investors, focused, decisive, and in control.
Although Warriors will listen to advisors, they make their own decisions and rely on their own
instincts and resources to guide them. Warriors often have difficulty recognizing the difference
between what appears to be an adversary and a worthy opponent. A worthy opponent should
be embraced as an opportunity to put down the sword and recognize the potential for growth
and transformation being offered in disguise. Worthy opponents are most easily recognized as
the person with whom you have the greatest conflict. When we are willing to step back and
recognize the lesson and truth this person has to teach, even when it is disguised as conflict,
their presence is worthy of our attention. When we recognize the conflict as an opportunity for
growth, our “opponent” has, in fact, served us. The world is filled with Warrior types, who run the
gamut from enjoying the sport of business and the skillful art of negotiating to those whose
single-minded intent is simply to win at any cost.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Archetype2", 
				'Value' => "Creator/Artists are on a spiritual or artistic path. They often find living in the material world
difficult and frequently have a conflicted love/hate relationship with money. They love money for
the freedom it buys them but have little or no desire to participate in the material world. The
Creator/Artist often overly identifies with the interior world and may even despise those who live
in the material world. Their negative beliefs about materialism only create a block to the very key
to the freedom they so desire. Creator/artists most fear being inauthentic or not being true to
themselves. The Creator/Artist is constantly struggling for financial survival. This is not because
they lack talent or ambition. Rather, they are stuck in a belief system that disempowers their
ability to manifest money. Too many people on the creative or artistic path feel that money is
bad or lacking in spirituality. This is only true to the extent that one believes it is true. And to the
extent that Creator/Artists maintain this belief system, they are limiting themselves and creating
a block to the flow of money. The Creators/Artists who work to integrate the spiritual with the
material world will find an end their struggles. Since they have often spent much of their time
and paid much attention to their inner journeys and creative potential, Creators/Artists already
possesses many of the qualities necessary to become Magicians. This type most needs to
accept the world she lives in and embrace in all its many dimensions. To stop suffering from the
tension we feel between the spiritual and material worlds, we must learn to embrace both worlds
as part of our own duality.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Archetype3", 
				'Value' => "Tyrants use money to control people, events, and circumstances. The Tyrant hoards money,
using it to manipulate and control others. Although Tyrants may have everything they need or
desire, they never feel complete, comfortable, or at peace. The Tyrant’s greatest fear is loss of
control. Tyrants are often overdeveloped Warriors who have become highly invested in their
need for control and dominance. While Warriors are often heroic in their true concern for others’
welfare, Tyrants are purely self interested. This type is interested in power and control for its
own sake and will forsake other people if necessary to gain more of it. Throughout history, the
Tyrant has emerged as the ruler who dominates and destroys with no sign of remorse. Today
Tyrants are the political leaders, businesspeople, or family figureheads who use whatever
means necessary to win at all costs. The Tyrant is a master manipulator of both people and
money. Perhaps it’s because the Tyrant type is often the most financially successful image we
have in our society that so many of us believe that money is the root of all evil. Television and
the media do their part to further convince us that although we may think we want more money,
we just need to look at what’s become of those who actually have it. It’s enough to make
anyone hesitate. Tyrants, however, are not as rich as they appear. Sure, they have everything
money can buy (which often does include beautiful people) and never have to worry about
paying the phone bill, but they lack many things that money cannot buy. They are often, in spite
of their apparent success, very fearful and rarely feel any sense of fulfillment. The Tyrant suffers
from a condition I call 'chronic-not-enoughness.'", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Archetype4", 
				'Value' => "The Innocent takes the ostrich approach to money matters. Innocents often live in denial,
burying their heads in the sand so they won't have to see what is going on around them. The
Innocent is easily overwhelmed by financial information and relies heavily on the advice and
opinions of others. Innocents are perhaps the most trusting of all the money archetypes
because they do not see people or situations for what they are. They are not unlike small
children in the sense that they have not yet learned to judge or discern other’s motives or
behavior. While this trait can be very endearing, it is also precarious for an adult trying to cope
in the real world. We all start out our journey in life as innocents. However, as we grow and
develop, the veil of innocence is lifted and replaced by our experience with the outer world.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Archetype5", 
				'Value' => "The Fool plays by a different set of rules altogether. A gambler by nature, the Fool is always
looking for a windfall of money by taking financial shortcuts. Even though the familiar adage “a
fool and his money are soon parted” often comes true, Fools often win because they are willing
to throw the dice; they are willing to take chances. The Fool is really a combination of the
Innocent and the Warrior. Like the Innocent, the Fool is often judgment impaired and has
difficulty seeing the truth about things. An adventurer, the Fool gets caught up in the enthusiasm
of the moment, caring little for the details. The primary difference between Fools and Innocents
is that Fools are relatively fearless in their endeavors and remain eternal optimists regardless of
the circumstances. In this manner, Fools are like Warriors in that they seem to always land on
their feet and are not easily defeated. The Fool also sets out to conquer the world but is easily
distracted and lacks the discipline of the Warrior. The Fool is much more interested in money
making as a sport or form of recreation than as a serious endeavor. Fools would happily give
the shirt off their backs only to realize later that it wasn’t their shirt or that it was their last. The
Fool does possess some rather remarkable qualities that if mastered make her quite capable of
becoming a Magician. The Fool lives very much in the moment and is quite unattached to future
outcome. Most of what Fools pursue is for the simple pleasure of doing it. Most of us could learn
from this characteristic of the Fool. However, until the Fool becomes enlightened he will
continue to attract money easily, only to have it quickly slip through his fingers because he’s
simply not paying attention.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Archetype6", 
				'Value' => "Victims are prone to living in the past and blaming their financial woes on external factors.
Passive-aggressive (prone to acting out their feelings in passive ways rather than through direct
action) in nature, Victims often appear disguised as Innocents, because they seem so
powerless and appear to want others to take care of them. However, this appearance is often
either a conscious or subconscious ploy to get others to do for them what they refuse to do for
themselves. Victims generally have a litany of excuses for why they are not more successful,
and they are all based on their historical mythology. That is not to say that bad things haven’t
actually happened to the Victim. More often than not, Victims have been abused, betrayed, or
have suffered some great loss. The problem is that they have never processed faced their pain,
and so it has turned on them. Victims are always looking for someone to rescue them, because
they believe they have suffered enough. They carry a sense of entitlement: 'I paid my dues,
look at my battle scars, where's my due'?", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Archetype7", 
				'Value' => "The Magician is the ideal money type. Using a new and ever-changing set of dynamics both in
the material world and in the world of the Spirit, Magicians know how to transform and manifest
their own financial reality. At our best, when we are willing to claim our own power, we are all
Magicians. The archetype that is active in your life now is the place you need to grow from. By
understanding your own personal mythology and the history behind your current money type,
you will become conscious of patterns and behavior that are preventing you from having the
relationship with money you desire. When you have reached the point of understanding and
have become aware of all that you need to know at this point on your journey, you will be ready
to transform your newly acquired consciousness into the reality of your life. The Magician is fully
awake and aware of herself and the world around her. The Magician is armed with the
knowledge of the past, has made peace with his personal history, and understands that his
source of power exists within in his ability to see and live the truth of who he is. Magicians know
the source of power to manifest lies in their ability to tap into their Higher Power. With faith, love,
and patience, the Magician simply waits in certainty with the knowledge that all our needs are
met all the time. Magicians embrace the inner life as the place of spiritual wealth and the outer
life as the expression of enlightenment in the material world. They are infinitely connected.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Archetype8", 
				'Value' => "Martyrs are so busy taking care of others’ needs that they often neglect their own. Financially
speaking, Martyrs generally do more for others than they do for themselves. They often rescue
others (a child, spouse, friend, partner) from some circumstance or other. However, Martyrs do
not always let go of what they give and are repeatedly let down when others fail to meet up to
their expectations. They have formed an unconscious attachment to their own suffering. The
Martyr moves between two distinctly different energies: one that seeks to be in control and
control others and the other being the wounded, often very needy, child. Martyrs tend to be
perfectionists and have high expectations of themselves and of others, which makes them quite
capable of realizing their dreams because they put so much energy into needing to be right.
Like Victims, Martyrs often live in high drama, experience a lot of highs and lows, and struggle
with their attachment to negative experience. They see the glass as half empty instead of half
full. Their focus on the negative often keeps them from realizing the deep wisdom that lies within
their experience. Martyrs who are willing to do their own work to heal their woundedness have
the capacity to become gifted healers and powerful manifestors — money Magicians.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "Closing", 
				'Value' => "", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "CTA Button Text", 
				'Value' => "Contact Me Now!", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "C. Archetype", 
				'Field' => "CTA Hyperlink", 
				'Value' => "https://www.mindfulmoneycoaching.com/contact-me/", 
			);		
			
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Enabled", 
				'Value' => "Yes", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Overview Title", 
				'Value' => "How’s your relationship with money?", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Overview Image", 
				'Value' => "https://www.mindfulmoneycoaching.com/wp-content/uploads/2018/06/Ilana-The-Money-Coach9.png", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Intro", 
				'Value' => "Our relationship with money shows up in every aspect of our lives – at home, in our relationships and in business – and having a healthy relationship will allow you to:", 
			);	
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item1 Title", 
				'Value' => "Improve Your Relationship With Money", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item1 Image", 
				'Value' => "https://www.mindfulmoneycoaching.com/wp-content/uploads/2018/05/Relationship-with-money.jpg", 
			);
			
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item1 Description", 
				'Value' => "If money was person how would you describe your relationship with them? Is it a healthy relationship? Does it dominate you, or do you use it to influence and control others?", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item2 Title", 
				'Value' => "Make Good Financial Decisions", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item2 Image", 
				'Value' => "https://www.mindfulmoneycoaching.com/wp-content/uploads/2018/05/Make-good-financial-decisions.jpg", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item2 Description", 
				'Value' => "Once you understand your relationship with money, we can start setting in place the tools you’ll need to make good financial decisions and live a prosperous future.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item3 Title", 
				'Value' => "Discover Your True Purpose", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item3 Image", 
				'Value' => "https://www.mindfulmoneycoaching.com/wp-content/uploads/2018/05/True-Purpose.jpg", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item3 Description", 
				'Value' => "'I'll show you how to develop a great relationship with money and set in motion the action steps you need to take in order live your true purpose.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item4 Title", 
				'Value' => "Visualize And Plan Your Success", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item4 Image", 
				'Value' => "https://www.mindfulmoneycoaching.com/wp-content/uploads/2018/05/Visualise-your-Success.jpg", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item4 Description", 
				'Value' => "Being able to visualize your dreams is a critical step to achieving them. We'll create goals and vision boards, it’s fun and inspiring.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item5 Title", 
				'Value' => "", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item5 Image", 
				'Value' => "", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item5 Description", 
				'Value' => "", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item6 Title", 
				'Value' => "", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item6 Image", 
				'Value' => "", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item6 Description", 
				'Value' => "", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item7 Title", 
				'Value' => "", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item7 Image", 
				'Value' => "", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Item7 Description", 
				'Value' => "", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "Closing", 
				'Value' => "", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "CTA Button Text", 
				'Value' => "Ready To Get Started?", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "CTA Hyperlink", 
				'Value' => "https://www.mindfulmoneycoaching.com/contact-me/", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Enabled", 
				'Value' => "Yes", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Title", 
				'Value' => "Testimonials", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Image", 
				'Value' => "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR7-3ebJsjJffPKs_J7b_M8g6nJjuNS5ZA0XsT5fexWgJPGGiW3", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Intro", 
				'Value' => "Check out this Awesome Feedback", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Item1 Title", 
				'Value' => "Hellen Otieno", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Item1 Image", 
				'Value' => "https://www.mindfulmoneycoaching.com/wp-content/uploads/2018/05/t1.jpg", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Item1 Description", 
				'Value' => "Ilana's approach to money coaching is both practical and intuitive. She guided me to a greater understanding of myself and transformed my relationship with money for the better. She took me through the process of gaining clarity about where I was in life and to chart a way forward in my life. I am forever grateful to her for being part of this life-changing experience with me.", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Item2 Title", 
				'Value' => "Marnita Oppermann", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Item2 Image", 
				'Value' => "https://www.mindfulmoneycoaching.com/wp-content/uploads/2018/05/t2.jpg", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Item2 Description", 
				'Value' => "I met Ilana in August as we were both on the same Money Coaching training through the Money Coaching Institute in California. We had a connection, both being South African but also personally. I was Ilana’s practice client towards the end of the 4 month training. I have such respect for her coaching skills. She is naturally intuitive, notices the smallest details that I didn’t even notice and through her I have made so many realisations and had a number of breakthroughs that have made me move forward both in my personal life and in my coaching business. I highly recommend her as a money coach, she is absolute gold!", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Item3 Title", 
				'Value' => "Cathy Miras", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Item3 Image", 
				'Value' => "https://www.mindfulmoneycoaching.com/wp-content/uploads/2018/05/t4.jpg", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Item3 Description", 
				'Value' => "I would highly recommend many of my friends, especially those who are monetary challenged. You have given me positive outlook and encouragement. It’s a matter of taking action and making it happen now. The business may not be running yet, however, there is a feeling of assurance and confidence in me at the moment. I am driven and feel very positive about my vision.", 
			);

			
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "Closing", 
				'Value' => "", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "CTA Button Text", 
				'Value' => "Contact Me Now!", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "E. Testimonials", 
				'Field' => "CTA Hyperlink", 
				'Value' => "", 
			);	
			
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "Enabled", 
				'Value' => "Yes", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "Title", 
				'Value' => "What You’ll Get:", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "Image", 
				'Value' => "https://cdn5.vectorstock.com/i/1000x1000/27/09/special-offer-price-tag-icon-for-sale-vector-1552709.jpg", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "Intro", 
				'Value' => "'I'll show you how to develop a great relationship with money and set in motion the action steps you need to take in order live your true purpose.", 
			);	
			
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "Item1 Title", 
				'Value' => "Support and Accountability", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "Item1 Description", 
				'Value' => "As you develop your new, positive relationship with money, you’ll have access to regular check-in sessions and group meetings to share your journey with others.", 
			);	

			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "Item2 Title", 
				'Value' => "Mastermind Groups", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "Item2 Description", 
				'Value' => "Maximize utilization of existing assets and investments in warehouses, distribution centers, trucks, people or software systems.", 
			);	

			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "Item3 Title", 
				'Value' => "Financial assistance to those who can't afford it", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "Item3 Description", 
				'Value' => "Often those who need it most, can least afford it. If you find yourself in that situation don't despair.", 
			);	
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "Closing", 
				'Value' => "", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "CTA Button Text", 
				'Value' => "Reach Out To Me and Lets Get Started", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "CTA Hyperlink", 
				'Value' => "https://www.mindfulmoneycoaching.com/contact-me/", 
			);

			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Enabled", 
				'Value' => "Yes", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Title", 
				'Value' => "Bonus Offer", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Image", 
				'Value' => "http://wpprofitmagnets.com/wp-content/uploads/2014/01/ExclusiveBonusOffer.png", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Intro", 
				'Value' => "Even before you start coaching with me, you can benefit from:", 
			);	
			
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Item1 Title", 
				'Value' => "Free 30 Minute Consultation", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Item1 Description", 
				'Value' => "There's a lot to digest, so let's set-up a call and I'll talk you through your results and what action steps you can take to start your journey to a new and healthy relationship with money.", 
			);	

			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Item2 Title", 
				'Value' => "Free eBook", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Item2 Description", 
				'Value' => "Download my eBook, packed with tips and strategies to help you put into practice life-changing behaviors that will change your destiny.", 
			);	

			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Item3 Title", 
				'Value' => "Newsletter", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Item3 Description", 
				'Value' => "Join hundreds of other readers who benefit from my monthly Newsletter which offers practical advice to real-world issues in helping you make the transition to the life you deserve", 
			);	
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Closing", 
				'Value' => "If you'd like to start living the life you were destined for, it would be my privilege to start this amazing journey with you.", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "CTA Button Text", 
				'Value' => "Download My Free eBook Now", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "CTA Hyperlink", 
				'Value' => "https://www.mindfulmoneycoaching.com/wp-content/uploads/2018/05/Pocket-Money-e-Book-v2-A5-Size-Design2.pdf ", 
			);

	 
			
		// insert default data into mq cta table	
			foreach($data_insert_cta as $data){
				$wpdb->insert( 
					$table_prefix.TABLE_MQ_CTA,
					$data
				);
			}
			
			// add value to wordpress options to check later for plugin status active/deactive 
			add_option('mq_money_coach_status', 'ACTIVE' );
			add_option('mq_money_coach_plugin_version', '1.4' );
		}else{
			// plugin was deactivated but not uninstalled/deleted 
			update_option('mq_money_coach_status', 'ACTIVE' );
		}
	}

	/**
	 * De-activate plugin but do not delete Db tables 
	 * and default data
	*/
	public static function mq_plugin_deactivation( ) {
		// set option DE-ACTIVE so if again activating plugin no DB queries needs to run, as no data is deleted 
		update_option('mq_money_coach_status', 'DE-ACTIVE' );
	}
	
	
	
}
