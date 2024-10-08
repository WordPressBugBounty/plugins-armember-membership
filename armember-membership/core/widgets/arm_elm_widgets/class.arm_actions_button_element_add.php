<?php
namespace ElementorARMELEMENT\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Controls_Stack;

if(! defined('ABSPATH')) exit;

class arm_action_button_shortcode extends Widget_Base
{
	public function get_categories() {
		return [ 'armember' ];
	}

    public function get_name()
    {
        return 'arm-logout-button-shortcode';
    }

    public function get_title()
    {
        return esc_html('ARMember Logout','armember-membership').'<style>
        .arm_element_icon{
			display: inline-block;
		    width: 28px;
		    height: 28px;
		    background-image: url('.MEMBERSHIPLITE_IMAGES_URL.'/armember_icon.png);
		    background-repeat: no-repeat;
		    background-position: bottom;
			border-radius: 5px;
		}
        .arm_display_user_info .elementor-choices-label .elementor-screen-only{
			position: relative;
			top: 0;
		}
        </style>';
    }
    public function get_icon() {
		return 'arm_element_icon';
	}

    public function get_script_depends() {
		return [ 'elementor-arm-element' ];
	}
    protected function register_controls()
    {
        global $ARMemberLite,$wp,$wpdb,$armainhelper,$arm_member_forms,$arm_social_feature;
		$arm_form =array();
        $arm_form['Please select a valid form']='Select Form type';
		
        /**START Fetch all shortcode controls from DB */
        /*END*/
        $this->start_controls_section(
			'section_content',
			[
				'label' => esc_html__( 'ARMember Action Button', 'armember-membership' ),
			]
		);
        $this->add_control(
			'title',
			[
				'label' => esc_html__( 'Title', 'armember-membership' ),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
			]
		);
        
		$this->add_control(
			'arm_action_type',
			[
				'label' => esc_html__( 'Link type', 'armember-membership'),
				'type' => Controls_Manager::SELECT,
				'default' => 'link',
				'options' => [
                    'link' => esc_html__('Link', 'armember-membership'),
                    'button' => esc_html__('Button', 'armember-membership')
                ],
				'label_block' => true,
				
			]
		);
        $this->add_control(
			'arm_link_text',
			[
				'label' => esc_html__( 'Link Text', 'armember-membership' ),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
                'default'=>'Logout',
			]
		);
        $this->add_control(
			'arm_action_display_user',
			[
				'label' => esc_html__('Display User Info','armember-membership'),
				'type' => Controls_Manager::CHOOSE,
				'default' =>'true',
				'options' => [
					'true' => [
						'title' => esc_html__( 'Yes', 'armember-membership' ),
					],
					'false' => [
						'title' => esc_html__( 'No', 'armember-membership' ),
					],
				],
				'classes'=>'arm_display_user_info',
				
			]
		);
        $this->add_control(
			'arm_redirection_url',
			[
				'label' => esc_html__( 'Redirect After Logout', 'armember-membership' ),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
                'default'=> ARMLITE_HOME_URL,
			]
		);
		$this->add_control(
			'arm_link_css',
			[
				'label' => esc_html__( 'Link CSS', 'armember-membership'),
				'type' => Controls_Manager::TEXTAREA,
				'default' => '',
				'label_block' => true,
				'classes'=>'',
			]
		);
		$this->add_control(
			'arm_link_hover_css',
			[
				'label' => esc_html__( 'Link Hover CSS', 'armember-membership'),
				'type' => Controls_Manager::TEXTAREA,
				'default' => '',
				'label_block' => true,
				'classes'=>'',
			]
		);
		

		$this->end_controls_section();
    }

	protected function render()
	{
		$settings = $this->get_settings_for_display();

		echo '<h5 class="title">';
		echo $settings['title']; //phpcs:ignore
		echo '</h5>';
		echo '<div class="arm_select">';
			$arm_shortcode='';
		
            echo do_shortcode('[arm_logout  type="'.$settings['arm_action_type'].'" user_info="'.$settings['arm_action_display_user'].'" label="'.$settings['arm_link_text'].'" redirect_to="'.$settings['arm_redirection_url'].'" link_css="'.$settings['arm_link_css'].'" link_hover_css="'.$settings['arm_link_hover_css'].'"]');

		echo '</div>';
	}
}
