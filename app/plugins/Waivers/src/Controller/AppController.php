<?php

declare(strict_types=1);

namespace Waivers\Controller;

use App\Controller\AppController as BaseController;

/**
 * Waivers Plugin AppController
 * 
 * Base controller for the Waiver Tracking System. Provides foundational
 * security architecture and component management for all controllers within
 * the Waivers plugin.
 * 
 * The Waivers AppController extends the main KMP application controller to
 * inherit core security features while adding Waivers-specific component
 * configuration and integration patterns.
 * 
 * ## Core Security Architecture:
 * - **Authentication Integration**: User authentication for waiver access
 * - **Authorization Framework**: Permission-based access control
 * - **Component Loading**: Standardized component initialization
 * - **Security Baseline**: Consistent security patterns
 * 
 * ## Plugin Architecture:
 * This base controller provides the foundation for all Waivers plugin controllers,
 * ensuring consistent security implementation, component availability, and
 * integration with the broader KMP application architecture.
 * 
 * @package Waivers\Controller
 * @see \App\Controller\AppController For main application controller inheritance
 */
class AppController extends BaseController {}
