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
namespace Cantiga\CoreBundle\Controller;

use Cantiga\CoreBundle\Api\Actions\FormAction;
use Cantiga\CoreBundle\Api\Controller\AreaPageController;
use Cantiga\CoreBundle\CoreExtensions;
use Cantiga\CoreBundle\CoreSettings;
use Cantiga\CoreBundle\Entity\Intent\AreaProfileIntent;
use Cantiga\CoreBundle\Form\AreaProfileForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @Route("/area/{slug}/profile")
 * @Security("has_role('ROLE_AREA_MEMBER')")
 */
class AreaProfileController extends AreaPageController
{
	const REPOSITORY = 'cantiga.core.repo.project_area';
	
	public function initialize(Request $request, AuthorizationCheckerInterface $authChecker)
	{
		$this->breadcrumbs()->workgroup('area');
	}
	
	/**
	 * @Route("/editor", name="area_profile_editor")
	 */
	public function editorAction(Request $request)
	{
		$this->breadcrumbs()->entryLink($this->trans('Profile editor', [], 'pages'), 'area_profile_editor', ['slug' => $this->getSlug()]);
		$area = $this->getMembership()->getItem();
		$repo = $this->get(self::REPOSITORY);
		$territoryRepo = $this->get('cantiga.core.repo.project_territory');
		$territoryRepo->setProject($area->getProject());
		$formModel = $this->extensionPointFromSettings(CoreExtensions::AREA_FORM, CoreSettings::AREA_FORM);		
		
		$intent = new AreaProfileIntent($area, $repo);
		$action = new FormAction($intent, new AreaProfileForm($this->getProjectSettings(), $formModel, $territoryRepo));
		$action->slug($this->getSlug());
		return $action->action($this->generateUrl('area_profile_editor', ['slug' => $this->getSlug()]))
			->template('CantigaCoreBundle:AreaProfile:editor.html.twig')
			->redirect($this->generateUrl('area_profile_editor', ['slug' => $this->getSlug()]))
			->formSubmittedMessage('AreaProfileSaved')
			->customForm($formModel)
			->onSubmit(function(AreaProfileIntent $intent) use($repo) {
				$intent->execute();
			})
			->run($this, $request);
	}
}
