<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

abstract class LDK_Site_Widget extends Widget_Base {
	protected $ldk_key = '';

	public function get_name() {
		return 'ldk-' . $this->ldk_key;
	}

	public function get_title() {
		$s = ldk_site_schema();
		return $s[ $this->ldk_key ]['title'];
	}

	public function get_icon() {
		$s = ldk_site_schema();
		return $s[ $this->ldk_key ]['icon'];
	}

	public function get_categories() {
		return array( 'ldk' );
	}

	protected function ldk_control_args( $type, $label, $default ) {
		$a = array( 'label' => $label );
		switch ( $type ) {
			case 'textarea':
				$a['type'] = Controls_Manager::TEXTAREA;
				$a['rows'] = 4;
				break;
			case 'number':
				$a['type'] = Controls_Manager::NUMBER;
				break;
			case 'image':
				$a['type']    = Controls_Manager::MEDIA;
				$default      = array( 'url' => (string) $default );
				break;
			case 'select':
				$a['type']    = Controls_Manager::SELECT;
				$a['options'] = ldk_site_icon_choices();
				break;
			case 'url':
				$a['type']        = Controls_Manager::TEXT;
				$a['description'] = 'Use https://…, pg:contato (página do site), wa (WhatsApp)';
				break;
			default:
				$a['type'] = Controls_Manager::TEXT;
		}
		$a['default'] = $default;
		return $a;
	}

	protected function register_controls() {
		$s = ldk_site_schema();
		$this->start_controls_section( 'content', array( 'label' => 'Conteúdo', 'tab' => Controls_Manager::TAB_CONTENT ) );
		foreach ( $s[ $this->ldk_key ]['fields'] as $key => $f ) {
			if ( 'repeater' === $f[0] ) {
				$rep = new Repeater();
				foreach ( $f[3] as $sk => $st ) {
					$rep->add_control( $sk, $this->ldk_control_args( $st, ucfirst( $sk ), 'select' === $st ? 'star' : ( 'image' === $st ? '' : '' ) ) );
				}
				$rows = array();
				foreach ( $f[2] as $row ) {
					foreach ( $f[3] as $sk => $st ) {
						if ( 'image' === $st && isset( $row[ $sk ] ) ) {
							$row[ $sk ] = array( 'url' => $row[ $sk ] );
						}
					}
					$rows[] = $row;
				}
				$first = array_key_exists( 'title', $f[3] ) ? 'title' : ( array_key_exists( 'q', $f[3] ) ? 'q' : key( $f[3] ) );
				$this->add_control( $key, array( 'label' => $f[1], 'type' => Controls_Manager::REPEATER, 'fields' => $rep->get_controls(), 'default' => $rows, 'title_field' => '{{{ ' . $first . ' }}}' ) );
			} else {
				$this->add_control( $key, $this->ldk_control_args( $f[0], $f[1], $f[2] ) );
			}
		}
		$this->end_controls_section();
	}

	protected function render() {
		echo ldk_site_render( $this->ldk_key, $this->get_settings_for_display() ); // phpcs:ignore
	}
}

class LDK_Site_W_pagehead extends LDK_Site_Widget {
	protected $ldk_key = 'pagehead';
}

class LDK_Site_W_hero extends LDK_Site_Widget {
	protected $ldk_key = 'hero';
}

class LDK_Site_W_marquee extends LDK_Site_Widget {
	protected $ldk_key = 'marquee';
}

class LDK_Site_W_services extends LDK_Site_Widget {
	protected $ldk_key = 'services';
}

class LDK_Site_W_steps extends LDK_Site_Widget {
	protected $ldk_key = 'steps';
}

class LDK_Site_W_stats extends LDK_Site_Widget {
	protected $ldk_key = 'stats';
}

class LDK_Site_W_clients extends LDK_Site_Widget {
	protected $ldk_key = 'clients';
}

class LDK_Site_W_panel extends LDK_Site_Widget {
	protected $ldk_key = 'panel';
}

class LDK_Site_W_about extends LDK_Site_Widget {
	protected $ldk_key = 'about';
}

class LDK_Site_W_cta extends LDK_Site_Widget {
	protected $ldk_key = 'cta';
}

class LDK_Site_W_contact extends LDK_Site_Widget {
	protected $ldk_key = 'contact';
}

class LDK_Site_W_diag extends LDK_Site_Widget {
	protected $ldk_key = 'diag';
}

class LDK_Site_W_posts extends LDK_Site_Widget {
	protected $ldk_key = 'posts';
}

class LDK_Site_W_links extends LDK_Site_Widget {
	protected $ldk_key = 'links';
}

class LDK_Site_W_faq extends LDK_Site_Widget {
	protected $ldk_key = 'faq';
}

class LDK_Site_W_testimonials extends LDK_Site_Widget {
	protected $ldk_key = 'testimonials';
}

class LDK_Site_W_social extends LDK_Site_Widget {
	protected $ldk_key = 'social';
}

class LDK_Site_W_pains extends LDK_Site_Widget {
	protected $ldk_key = 'pains';
}

class LDK_Site_W_quick extends LDK_Site_Widget {
	protected $ldk_key = 'quick';
}

class LDK_Site_W_feed extends LDK_Site_Widget {
	protected $ldk_key = 'feed';
}
