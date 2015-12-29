<?php
/*
 * This file is part of Cantiga Project. Copyright 2015 Tomasz Jedrzejewski.
 *
 * Cantiga Project is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * Cantiga Project is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Foobar; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
namespace Cantiga\Metamodel;

use Symfony\Component\Routing\RouterInterface;

/**
 * Generating links in JavaScript is painful, if we use Symfony to manage them. Instead of trying to teach JS,
 * how to do that, we simply build all the necessary links for a data row in PHP and send them together with the
 * data set.
 * 
 * <p>This class offers a way to enhance the data set with all the rows with the links.
 * 
 * <ol>
 *  <li>Specify all the links with {@link #link()} method,</li>
 *  <li>Process the data set produced by the repositoy with {@link #process()} method,</li>
 *  <li>Use the links in the JavaScript.</li> 
 * </ol>
 * 
 * <p>The fresh instance of DataRoutes can be obtained directly from the controller helper method
 * {@link Cantiga\CoreBundle\Api\Controller\CantigaController#dataRoutes()}.
 *
 * @author Tomasz Jędrzejewski
 */
class DataRoutes
{
	/**
	 * @var RouterInterface
	 */
	private $router;
	/**
	 * Configuration of links to be generated
	 * @var array
	 */
	private $links = array();
	
	public function __construct(RouterInterface $router)
	{
		$this->router = $router;
	}

	/**
	 * Adds a new link definition. The link will appear in the row under <tt>$key</tt> name, and
	 * it will be generated from <tt>$routeName</tt> route. The <tt>$args</tt> are passed to the
	 * router. If the argument value is prefixed with <tt>::</tt>, it is treated as a reference
	 * to the specified key in the data row.
	 * 
	 * <p>Example: the argument <tt>::id</tt> is the placeholder, where the ID of the row should
	 * be placed.
	 * 
	 * @param string $key The link will be saved in the row under this name.
	 * @param string $routeName The name of Symfony route used for building the link.
	 * @param array $args Arguments for the router.
	 * @return Cantiga\Metamodel\DataRoutes
	 */
	public function link($key, $routeName, array $args = array())
	{
		$this->links[] = ['key' => (string) $key, 'route' => (string) $routeName, 'args' => $args];
		return $this;
	}
	
	/**
	 * Enhances the given data set with the links, and returns the modified data set. The method
	 * is compatible with the output format produced by {@link Cantiga\Metamodel\DataTable}
	 * class, but can be also used for plain row sets.
	 * 
	 * @param array $list Data set
	 * @return array
	 */
	public function process(array $list)
	{
		$parsed = $list;
		if (isset($list['draw']) && isset($list['data'])) {
			$parsed = &$list['data'];
		}
		foreach ($parsed as &$item) {
			foreach ($this->links as $link) {
				$args = $link['args'];
				foreach ($args as $k => &$v) {
					if (strpos($v, '::') === 0) {
						$extractor = substr($v, 2);
						$v = isset($item[$extractor]) ? $item[$extractor] : null;
					}
				}
				$item[$link['key']] = $this->router->generate($link['route'], $args);
			}
		}
		return $list;
	}
}
