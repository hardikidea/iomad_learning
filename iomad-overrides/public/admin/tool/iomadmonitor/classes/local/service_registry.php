<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Validated directed acyclic graph of platform services.
 *
 * @package tool_iomadmonitor
 */
final class service_registry {
    /** @var array<string, service_descriptor> Services by stable ID. */
    private array $services = [];

    /**
     * Add one service.
     *
     * @param service_descriptor $service Service.
     */
    public function add(service_descriptor $service): void {
        if (isset($this->services[$service->id()])) {
            throw new \InvalidArgumentException('Duplicate service ID: ' . $service->id());
        }
        $this->services[$service->id()] = $service;
    }

    /**
     * Add all services exposed by a provider.
     *
     * @param service_provider_interface $provider Provider.
     */
    public function add_provider(service_provider_interface $provider): void {
        foreach ($provider->services() as $service) {
            if (!$service instanceof service_descriptor) {
                throw new \InvalidArgumentException('Service providers may return descriptors only.');
            }
            $this->add($service);
        }
    }

    /**
     * Validate dependencies and return dependency-first order.
     *
     * @return service_descriptor[]
     */
    public function ordered(): array {
        foreach ($this->services as $service) {
            foreach ($service->dependencies() as $dependency) {
                if (!isset($this->services[$dependency])) {
                    throw new \InvalidArgumentException(
                        "Service {$service->id()} has missing dependency {$dependency}.",
                    );
                }
            }
        }

        $states = [];
        $ordered = [];
        foreach (array_keys($this->services) as $id) {
            $this->visit($id, $states, $ordered);
        }
        return array_values($ordered);
    }

    /**
     * Privacy-safe catalogue records.
     *
     * @return array
     */
    public function catalogue(): array {
        return array_map(
            static fn(service_descriptor $service): array => $service->to_array(),
            $this->ordered(),
        );
    }

    /**
     * Catalogue records visible under an explicit policy context.
     *
     * @param service_visibility_policy $policy Policy.
     * @param bool $authenticated Authenticated caller.
     * @param string[] $capabilities Granted capabilities.
     * @param bool $hascompanycontext Active company context.
     * @return array
     */
    public function visible_catalogue(
        service_visibility_policy $policy,
        bool $authenticated,
        array $capabilities,
        bool $hascompanycontext,
    ): array {
        $records = [];
        foreach ($this->ordered() as $service) {
            if ($policy->can_view($service, $authenticated, $capabilities, $hascompanycontext)) {
                $records[] = $service->to_array();
            }
        }
        return $records;
    }

    /**
     * Depth-first graph validation.
     *
     * @param string $id Service ID.
     * @param array $states Visitation state.
     * @param array $ordered Ordered output.
     */
    private function visit(string $id, array &$states, array &$ordered): void {
        if (($states[$id] ?? '') === 'complete') {
            return;
        }
        if (($states[$id] ?? '') === 'visiting') {
            throw new \InvalidArgumentException('Service dependency cycle includes ' . $id . '.');
        }
        $states[$id] = 'visiting';
        foreach ($this->services[$id]->dependencies() as $dependency) {
            $this->visit($dependency, $states, $ordered);
        }
        $states[$id] = 'complete';
        $ordered[$id] = $this->services[$id];
    }
}
