<?php

/**
 * Simple Service Container for bbPress Forum AI Bot
 */
class Bbpress_Forum_AI_Bot_Service_Container {

    private $services = [];
    private $instances = []; // Store instantiated services

    /**
     * Register a service definition.
     *
     * @param string $id The service ID.
     * @param callable $factory A callable that returns the service instance.
     */
    public function register( $id, callable $factory ) {
        $this->services[ $id ] = $factory;
        unset( $this->instances[ $id ] ); // Clear any previous instance if re-registering
    }

    /**
     * Get a service instance from the container.
     *
     * @param string $id The service ID.
     * @return mixed The service instance.
     * @throws InvalidArgumentException If the service ID is not registered.
     * @throws RuntimeException If a circular dependency is detected during resolution (optional).
     */
    public function get( $id ) {
        // Return instance if already created
        if ( isset( $this->instances[ $id ] ) ) {
            return $this->instances[ $id ];
        }

        if ( ! $this->has( $id ) ) {
            throw new InvalidArgumentException( sprintf( 'Service "%s" is not registered in the container.', $id ) );
        }

        // Check if it's a factory callable
        if ( is_callable( $this->services[ $id ] ) ) {
            $factory = $this->services[ $id ];

            // --- Simple Circular Dependency Break ---
            // Create the instance by calling the factory
            $instance = $factory( $this );
            // Store the instance *before* returning, making it available for subsequent calls
            $this->instances[ $id ] = $instance;

            return $instance;
            // --- End Simple Circular Dependency Break ---

        } else {
             // If not callable, assume it's a pre-resolved instance or parameter.
             // This part might need refinement if non-callable services are registered differently.
             // For now, return the stored value if it exists.
             return $this->services[ $id ];
        }
    }

    /**
     * Check if a service definition exists (factory or pre-defined value).
     *
     * @param string $id The service ID.
     * @return bool True if the service definition exists, false otherwise.
     */
    public function has( $id ) {
        return isset( $this->services[ $id ] );
    }
}