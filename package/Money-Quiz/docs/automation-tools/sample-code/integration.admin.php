<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
 global $wpdb;
 $table_prefix = $wpdb->prefix;
 
 //$sql = "ALTER TABLE ".$table_prefix.TABLE_MQ_MASTER." ADD `Classic` VARCHAR(5) NOT NULL AFTER `Full`";
 //$sql = "DELETE FROM ".$table_prefix.TABLE_MQ_MASTER." WHERE `Prospect_ID` = 8";
 
// $wpdb->query($sql);
 
  /*		// Create CTA table 
			$sql = "CREATE TABLE IF NOT EXISTS ".$table_prefix.TABLE_MQ_CTA." (
			  `ID` int(11) NOT NULL AUTO_INCREMENT,
    		  `Section` varchar(55) NOT NULL,
			  `Field` varchar(555) NOT NULL,
			  `Value` text NOT NULL,
			  PRIMARY KEY (`ID`)
			) ".$charset_collate." ;";
			$wpdb->query($sql);
			
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
				'Value' => "Earn more money from your natural strengths and gifts", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item1 Description", 
				'Value' => "Specifically how you can use your money personality in your marketing and business to bring more money in with less effort! (It\'s totally different for everyone)", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item2 Title", 
				'Value' => "Stop repeating financial mistakes and overcome your money challenges", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item2 Description", 
				'Value' => "You\'ll be surprised how your personality leaks money unnecessarily or sabotages easy wins. Each archetype combination does it differently.", 
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
				'Value' => "Manage your money effectively", 
			);
			$data_insert_cta[] =	array( 
				'Section' => "B. Benefits", 
				'Field' => "Item4 Description", 
				'Value' => "", 
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
					'Value' => "Contact Me Now!", 
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
				'Value' => "Why You Should Contact Me", 
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
				'Value' => "", 
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
				'Value' => "", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "D. Promotion", 
				'Field' => "CTA Hyperlink", 
				'Value' => "", 
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
				'Value' => "Contact Me Now!", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "F. Special Offer", 
				'Field' => "CTA Hyperlink", 
				'Value' => "", 
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
				'Value' => "A free 30 min consultation with me to help make sense of it all and prioritze your next steps	", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Item1 Description", 
				'Value' => "", 
			);	

			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Item2 Title", 
				'Value' => "Download my eBook and start creating a healthy relationship with money", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Item2 Description", 
				'Value' => "", 
			);	

			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Item3 Title", 
				'Value' => "Free subscription to my monthly Newsletter and receive regular tips and strategies to help you succeed", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Item3 Description", 
				'Value' => "", 
			);	
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "Closing", 
				'Value' => "", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "CTA Button Text", 
				'Value' => "Contact Me Now!", 
			);		 
			$data_insert_cta[] =	array( 
				'Section' => "G. Bonus Offer", 
				'Field' => "CTA Hyperlink", 
				'Value' => "", 
			);

	 
			
		// insert default data into mq cta table	
			foreach($data_insert_cta as $data){
				$wpdb->insert( 
					$table_prefix.TABLE_MQ_CTA,
					$data
				);
			}
	*/ 
	
$select_feilds_arr = array();
if($post_data[55] != "" && $post_data[57] != "" && $post_data[60] != ""){ 

	require_once('vendor/autoload.php');

	$mailerliteClient = new \MailerLiteApi\MailerLite($post_data[57]);

	$groupsApi = $mailerliteClient->groups();
	//$groups = $groupsApi->get(); // returns array of groups
	//echo '<pre>';
 
	$fieldsApi = $mailerliteClient->fields();
	$fields = $fieldsApi->get(); // returns array of fields
	foreach($fields as $m_field){
		//print_r($m_field);
		//break;
		$select_feilds_arr[$m_field->key] = $m_field->title;	
	}
	//print_r($select_feilds_arr);
	
	
	 
 
}
	
 
 ?>
  
<div class=" mq-container">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<div class="mq-intro">
		<?php echo $save_msg ?>
	</div>	
	<div class="clear"></div>
	<form method="post" action="" novalidate="novalidate">
		<input name="action" value="integration" type="hidden">
		<?php wp_nonce_field( );?>		
		<table class="form-table mq-form-table " style="margin-top:-20px">
			<tbody>
				<!--<tr>
					<td scope="row">Automatically update your Mailing List with your MoneQuiz Prospects details and (where supported) add a tag 'MoneyQuiz' to indicate that the prospect was added to your mailing list after having taken then MoneyQuiz.</td>
				</tr>	
				<tr>
					<td scope="row">Some of the Supported Mailing Applications include…</td>
				</tr>
				<tr>
					<td scope="row" align="center"> <img src="<?php //echo plugins_url('assets/images/integration-images.png', __FILE__)?> " ></td>
				</tr> -->
				<tr>
					<td colspan="2" scope="row" ><h3>Integrate your Prospects with your Mailing Application</h3>  </td>
				</tr>
				<tr>
					<td colspan="2"  scope="row" >Automatically update your Mailing List with your MoneQuiz Prospects details and (where supported) add a tag 'MoneyQuiz' to indicate that the prospect was added to your mailing list after having taken then MoneyQuiz.  </td>
				</tr>

				<tr>
					<th scope="row"><label for="Prospect_Surname">Mailing Application</label></th>
					<td><select name="post_data[55]" class="regular-text">
							<option value="" <?php echo ($post_data[55]== ""? 'selected="selected"': '') ?> > Select Mailing Application </option>
							<option value="mailerlite" <?php echo ($post_data[55]== "mailerlite"? 'selected="selected"': '') ?> >Mailerlite </option>
						</select>
					</td>
				</tr>

	<!--			<tr>
					<th scope="row"> <label for="56">URL</label></th>
					<td><input name="post_data[56]" id="56" value="<?php echo $post_data[56]?>"  type="text" class="regular-text" > 
					</td>
				</tr> -->
				<tr>
					<th scope="row"> <label for="57">Enter API Key</label></th>
					<td><input name="post_data[57]" id="57" value="<?php echo $post_data[57]?>"  type="text" class="regular-text" > 
					<a href="https://app.mailerlite.com/integrations/api/" target="_new">Click here to find API key</a>
					</td>
				</tr>
				<tr>
					<th scope="row"> <label for="60">Subscriber group ID </label></th>
					<td><input name="post_data[60]" id="60" value="<?php echo $post_data[60]?>"  type="text" class="regular-text" > 
					<a href="https://app.mailerlite.com/integrations/api/" target="_new">Click here to find group id</a>, only numeric value
					</td>
				</tr>
		 
			<?php if($post_data[55] != "" && $post_data[57] != "" && $post_data[60] != ""){ ?>
				<!--<tr>
					<th scope="row"><label for="Prospect_Surname">Select Mailing List/Group</label></th>
					<td><select name="post_data[60]" class="regular-text">
							<option value="" <?php echo ($post_data[60]== ""? 'selected="selected"': '') ?> > Select  </option>
							<option value="mailerlite" <?php echo ($post_data[60]== "mailerlite"? 'selected="selected"': '') ?> >Mailerlite group </option>
						</select>
					</td>
				</tr>-->	
				<tr>
					<th scope="row">Select Fields to Update</th>
					<td align="">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>Select the Matching Field</b></td>
				</tr>				 					
				<tr>
					<th scope="row"> Prospect's First Name</th>
					<td><input type="radio" <?php echo ($post_data[61]== "Yes"? 'checked="checked"': '') ?> name="post_data[61]" value="Yes">Yes &nbsp;
						<input type="radio" name="post_data[61]" value="No" <?php echo ($post_data[61]== "No"? 'checked="checked"': '') ?> >No 
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<select name="post_data[62]" >
							<option value="" <?php echo ($post_data[62]== ""? 'selected="selected"': '') ?> > Select  </option>
							<?php foreach($select_feilds_arr as $key_val=>$select_feild){
								echo '<option value="'.$key_val.'" '.($post_data[62]== $key_val? 'selected="selected"': '').' >'.$select_feild.' </option>';
							} ?>	
						</select>
					</td>
				</tr>	
				<tr>
					<th scope="row"> Prospect's Surname</th>
					<td><input type="radio" <?php echo ($post_data[63]== "Yes"? 'checked="checked"': '') ?> name="post_data[63]" value="Yes">Yes &nbsp;
						<input type="radio" name="post_data[63]" value="No" <?php echo ($post_data[63]== "No"? 'checked="checked"': '') ?> >No 
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<select name="post_data[64]" >
							<option value="" <?php echo ($post_data[64]== ""? 'selected="selected"': '') ?> > Select  </option>
							<?php foreach($select_feilds_arr as $key_val=>$select_feild){
								echo '<option value="'.$key_val.'" '.($post_data[64]== $key_val? 'selected="selected"': '').' >'.$select_feild.' </option>';
							} ?>
						</select>
					</td>
				</tr>	
				<tr>
					<th scope="row"> Prospect's Email</th>
					<td><input type="radio" <?php echo ($post_data[65]== "Yes"? 'checked="checked"': '') ?> name="post_data[65]" value="Yes">Yes &nbsp;
						<input type="radio" name="post_data[65]" value="No" <?php echo ($post_data[65]== "No"? 'checked="checked"': '') ?> >No 
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<select name="post_data[66]" >
							<option value="" <?php echo ($post_data[66]== ""? 'selected="selected"': '') ?> > Select  </option>
							<?php foreach($select_feilds_arr as $key_val=>$select_feild){
								echo '<option value="'.$key_val.'" '.($post_data[66]== $key_val? 'selected="selected"': '').' >'.$select_feild.' </option>';
							} ?>
						</select>
					</td>
				</tr>	

				<tr>
					<th scope="row"> Prospect's Tel</th>
					<td><input type="radio" <?php echo ($post_data[67]== "Yes"? 'checked="checked"': '') ?> name="post_data[67]" value="Yes">Yes &nbsp;
						<input type="radio" name="post_data[67]" value="No" <?php echo ($post_data[67]== "No"? 'checked="checked"': '') ?> >No 
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<select name="post_data[68]" >
							<option value="" <?php echo ($post_data[68]== ""? 'selected="selected"': '') ?> > Select  </option>
							<?php foreach($select_feilds_arr as $key_val=>$select_feild){
								echo '<option value="'.$key_val.'" '.($post_data[68]== $key_val? 'selected="selected"': '').' >'.$select_feild.' </option>';
							} ?>
						</select>
					</td>
				</tr>	
				<tr>
					<th scope="row"> Newsletter Option</th>
					<td><input type="radio" <?php echo ($post_data[69]== "Yes"? 'checked="checked"': '') ?> name="post_data[69]" value="Yes">Yes &nbsp;
						<input type="radio" name="post_data[69]" value="No" <?php echo ($post_data[69]== "No"? 'checked="checked"': '') ?> >No 
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<select name="post_data[70]" >
							<option value="" <?php echo ($post_data[70]== ""? 'selected="selected"': '') ?> > Select  </option>
							<?php foreach($select_feilds_arr as $key_val=>$select_feild){
								echo '<option value="'.$key_val.'" '.($post_data[70]== $key_val? 'selected="selected"': '').' >'.$select_feild.' </option>';
							} ?>
						</select>
					</td>
				</tr>	
				<tr>
					<th scope="row"> Consultation Option</th>
					<td><input type="radio" <?php echo ($post_data[71]== "Yes"? 'checked="checked"': '') ?> name="post_data[71]" value="Yes">Yes &nbsp;
						<input type="radio" name="post_data[71]" value="No" <?php echo ($post_data[71]== "No"? 'checked="checked"': '') ?> >No 
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<select name="post_data[72]" >
							<option value="" <?php echo ($post_data[72]== ""? 'selected="selected"': '') ?> > Select  </option>
							<?php foreach($select_feilds_arr as $key_val=>$select_feild){
								echo '<option value="'.$key_val.'" '.($post_data[72]== $key_val? 'selected="selected"': '').' >'.$select_feild.' </option>';
							} ?>
						</select>
					</td>
				</tr>	
				<tr>
					<th scope="row"> Add Tag "MoneyQuiz"</th>
					<td><input type="radio" <?php echo ($post_data[73]== "Yes"? 'checked="checked"': '') ?> name="post_data[73]" value="Yes">Yes &nbsp;
						<input type="radio" name="post_data[73]" value="No" <?php echo ($post_data[73]== "No"? 'checked="checked"': '') ?> >No 
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<select name="post_data[74]" >
							<option value="" <?php echo ($post_data[74]== ""? 'selected="selected"': '') ?> > Select  </option>
							<?php foreach($select_feilds_arr as $key_val=>$select_feild){
								echo '<option value="'.$key_val.'" '.($post_data[74]== $key_val? 'selected="selected"': '').' >'.$select_feild.' </option>';
							} ?>
						</select>
					</td>
				</tr>	
				<tr>
					<th scope="row"> Update Rule</th>
					<td><input type="radio" <?php echo ($post_data[75]== "All Records"? 'checked="checked"': '') ?> name="post_data[75]" value="All Records">All Records					&nbsp;&nbsp; <input type="radio" <?php echo ($post_data[75]== "Only if Newsletter Selected"? 'checked="checked"': '') ?> name="post_data[75]" value="Only if Newsletter Selected">Only if Newsletter Selected 
						 
					</td>
				</tr>	

				<tr>
					<td colspan="2"><b>Note: </b>Prospect's email will be checked if already added only then records will be updated, otherwise a new record will be added.<br>
					Please match all the above fields, otherwise they will not be updated.
					</td>
				</tr>
			<?php } ?>						
				
				<tr>
					<th scope="row">&nbsp;</th>
					<td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"></p></td>
				</tr>
				
				
				 <tr>
					<td colspan="2"  scope="row"><br><h3>Can't Find your Mailing Application</h3>If your Mailing Aplication is not listed above please use the order form to submit your request<br><a target="_new" href="https://goo.gl/forms/HR1jo4P9bpsw6wua2">https://goo.gl/forms/HR1jo4P9bpsw6wua2</a> </td>
				</tr>
				 <tr>
					<td colspan="2"  scope="row" align="center"> <a target="_new" href="https://goo.gl/forms/HR1jo4P9bpsw6wua2">Order your integration Now at this link or click on the button below</a> </td>
				</tr>
				 <tr>
					<td  colspan="2"  scope="row" align="center"> <a target="_new" href="https://goo.gl/forms/HR1jo4P9bpsw6wua2"><button name="renew_plugin" id="submit" class="button button-primary renew_plugin" value="Save Changes" type="submit">Order Now</button> </a> </td>
				</tr>
				 
				
			</tbody>
		</table>
		</form> 
	
<br> 
</div>
<!-- .wrap -->