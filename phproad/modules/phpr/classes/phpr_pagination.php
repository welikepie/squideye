<?php

	/**
	 * PHP Road
	 *
	 * PHP application framework
	 *
	 * @package		PHPRoad
	 * @author		Aleksey Bobkov, Andy Chentsov
	 * @since		Version 1.0
	 * @filesource
	 */

	/**
	 * PHP Road Pagination Class
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Pagination
	{
		private $_currentPageIndex;
		private $_pageSize;
		private $_rowCount;
		private $_pageCount;

		/**
		 * Creates a new Phpr_Pagination instance
		 * @param integer $PageSize Specifies a page size.
		 * @return Phpr_Pagination
		 */
		public function __construct( $PageSize = 20 )
		{
			$this->_currentPageIndex = 0;
			$this->_pageSize = $PageSize;
			$this->_rowCount = 0;
			$this->_pageCount = 1;
		}

		/**
		 * Applies the limitation rules to an active record object.
		 * @param Db_ActiveRecord $Obj Specifies the Active Record object to limit.
		 */
		public function limitActiveRecord( Db_ActiveRecord $Obj )
		{
			$Obj->limit( $this->getPageSize(), $this->getFirstPageRowIndex() );
		}

		/**
		 * Restores a pagination object from session or creates a new object.
		 * @param string $Name Specifies a name of the object in the session.
		 * @param integer $PageSize Specifies a page size.
		 */
		public static function fromSession( $Name, $PageSize = 20 )
		{
			if ( !Phpr::$session->has($Name) )
				Phpr::$session[$Name] = new Phpr_Pagination($PageSize);

			return Phpr::$session[$Name];
		}

		/**
		 * Evaluates the number of pages for the page size and row count specified in the object properties.
		 * @return integer
		 */
		private function evaluatePageCount( $PageSize, $RowCount )
		{
			$result = ceil($RowCount/$PageSize);

			if ( $result == 0 )
				$result = 1;

			return $result;
		}

		/**
		 * Re-evaluates the current page index value.
		 * @param integer $CurrentPage Specifies the current page value
		 * @param integer $PageCount Specifies the count value
		 * @return integer
		 */
		private function fixCurrentPageIndex( $CurrentPageIndex, $PageCount )
		{
			$lastPageIndex = $PageCount - 1;

			if ( $CurrentPageIndex > $lastPageIndex )
				$CurrentPageIndex = $lastPageIndex;

			return $CurrentPageIndex;
		}

		/**
		 * Sets the index of the current page.
		 * @param integer $Value Specifies the value to set.
		 */
		public function setCurrentPageIndex( $Value )
		{
			$lastPageIndex = $this->_pageCount - 1;

			if ( $Value < 0 )
				$Value = 0;

			if ( $Value > $lastPageIndex )
				$Value = $lastPageIndex;

			$this->_currentPageIndex = $Value;

			return $Value;
		}

		/**
		 * Returns the index of the current page.
		 * @return integer
		 */
		public function getCurrentPageIndex()
		{
			return $this->_currentPageIndex;
		}

		/**
		 * Sets the number of rows on a single page.
		 * @param integer $Value Specifies the value to set.
		 */
		public function setPageSize( $Value )
		{
			if ( $Value <= 0 )
				throw new Phpr_ApplicationException( "Page size is out of range" );

			$this->_pageSize = $Value;

			$this->_pageCount = $this->evaluatePageCount( $Value, $this->_rowCount );
			$this->_currentPageIndex = $this->fixCurrentPageIndex( $this->_currentPageIndex, $this->_pageCount );
		}

		/**
		 * Returns the number of rows on a single page.
		 * @return integer
		 */
		public function getPageSize()
		{
			return $this->_pageSize;
		}

		/**
		 * Sets the total number of rows.
		 * @param integer $RowCount Specifies the value to set.
		 */
		public function setRowCount( $Value )
		{
			if ( $Value < 0 )
				throw new Phpr_ApplicationException( "Row count is out of range" );

			$this->_pageCount = $this->evaluatePageCount( $this->_pageSize, $Value );
			$this->_currentPageIndex = $this->fixCurrentPageIndex( $this->_currentPageIndex, $this->_pageCount );
			$this->_rowCount = $Value;
		}

		/**
		 * Returns the total number of rows.
		 * @return integer
		 */
		public function getRowCount()
		{
			return $this->_rowCount;
		}

		/**
		 * Returns the index of the first row on the current page.
		 * @return integer
		 */
		public function getFirstPageRowIndex()
		{
			return $this->_pageSize*$this->_currentPageIndex;
		}
		
		public function getLastPageRowIndex()
		{
			$index = $this->getFirstPageRowIndex();
			$index += $this->_pageSize-1;

			if ($index > $this->_rowCount-1)
				$index = $this->_rowCount-1;
				
			return $index;
		}

		/**
		 * Returns the total number of pages.
		 * @return integer
		 */
		public function getPageCount()
		{
			return $this->_pageCount;
		}
	}

?>