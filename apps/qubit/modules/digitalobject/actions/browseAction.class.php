<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Browse list for digital objects
 *
 * @package    AccesstoMemory
 * @subpackage digitalobject
 * @author     David Juhasz <david@artefactual.com>
 */
class DigitalObjectBrowseAction extends DefaultBrowseAction
{
  // Arrays not allowed in class constants
  public static
    $FACETS = array(
      'mediatypes' =>
        array('type' => 'term',
              'field' => 'digitalObject.mediaTypeId',
              'size' => 10));

  protected function populateFacet($name, $ids)
  {
    switch ($name)
    {
      case 'mediatypes':
        $criteria = new Criteria;
        $criteria->add(QubitTerm::ID, array_keys($ids), Criteria::IN);

        foreach (QubitTerm::get($criteria) as $mediaType)
        {
          $this->types[$mediaType->id] = $mediaType->getName(array('cultureFallback' => true));
        }

        break;

      default:
        parent::populateFacet($name, $ids);
    }
  }

  public function execute($request)
  {
    // Force number of hits per page
    $request->limit = 30;

    parent::execute($request);

    // Create query object
    $this->queryBool->addMust(new \Elastica\Query\Term(array('hasDigitalObject' => true)));

    if (isset($this->getRoute()->resource))
    {
      $this->resource = $this->getRoute()->resource;
      $this->queryBool->addMust(new \Elastica\Query\Term(array('ancestors' => $this->resource->id)));
    }

    // Sorting
    switch ($request->sort)
    {
      case 'lastUpdated':
        $this->query->setSort(array('updatedAt' => 'desc'));

        break;

      case 'identifier':
        $this->query->setSort(array('identifier' => 'asc'));

        break;

      case 'alphabetic':
      default:
        $field = sprintf('i18n.%s.title.untouched', $this->context->user->getCulture());
        $this->query->setSort(array($field => 'asc'));
    }

    $this->query->setQuery($this->queryBool);

    // Filter drafts
    QubitAclSearch::filterDrafts($this->filterBool);

    // Set filter
    if (0 < count($this->filterBool->toArray()))
    {
      $this->query->setFilter($this->filterBool);
    }

    $resultSet = QubitSearch::getInstance()->index->getType('QubitInformationObject')->search($this->query);

    // Pager results
    $this->pager = new QubitSearchPager($resultSet);
    $this->pager->setPage($request->page ? $request->page : 1);
    $this->pager->setMaxPerPage($request->limit);
    $this->pager->init();

    $this->populateFacets($resultSet);
  }
}
