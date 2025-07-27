<?php

namespace Macopedia\Allegro\Model\ResourceModel;

class ProductCatalog extends AbstractResource
{
    /**
     * @param string $uri
     * @param array $params
     * @return array
     */
    public function get(string $uri, array $params = []): array
    {
        return $this->requestGet($uri, $params);
    }

    /**
     * @param string $uri
     * @param array $data
     * @return array
     */
    public function post(string $uri, array $data): array
    {
        return $this->requestPost($uri, $data);
    }

    /**
     * @param string $uri
     * @param array $data
     * @return array
     */
    public function delete(string $uri, array $data): array
    {
        return $this->requestDelete($uri, $data);
    }
}